<?php

require dirname( __FILE__ ) . '/onapp.cron.php';
class OnApp_UserModule_Cron_Invoices extends OnApp_UserModule_Cron {
	private $fromDate, $timeZoneOffset, $dueDate;

	protected  function run() {
		$this->getAdditionalFiles();
		$this->process();
	}

	private function process() {
		//get admin
		$qry   = 'SELECT
					`username`
				FROM
					`tbladmins`
				LIMIT 1';
		$res   = mysql_query( $qry );
		$admin = mysql_result( $res, 0 );
		//calculate invoice due date
		$this->dueDate = date( 'Ymd' );

		while( $client = mysql_fetch_assoc( $this->clients ) ) {
			$clientAmount = $this->getAmmount( $client );

			if( ! is_null( $clientAmount->total ) ) {
				$data = $this->generateInvoiceData( $clientAmount, $client );
				if( $data == false ) {
					continue;
				}
				$result = localAPI( 'CreateInvoice', $data, $admin );
				if( $result[ 'result' ] != 'success' ) {
					echo 'An Error occurred trying to create a invoice: ', $result[ 'result' ], PHP_EOL;
				}
				else {
					$qry = 'UPDATE
								`tblinvoiceitems`
							SET
								`relid` = :WHMCSUserID,
								`type` = "onappusers"
							WHERE
								`invoiceid` = :invoiceID';
					$qry = str_replace( ':WHMCSUserID', $client[ 'service_id' ], $qry );
					$qry = str_replace( ':invoiceID', $result[ 'invoiceid' ], $qry );
					full_query( $qry );
					$qry = 'INSERT INTO
								`onapp_itemized_invoices` (
									`whmcs_user_id`,
									`onapp_user_id`,
									`server_id`,
									`invoice_id`,
									`date`
								)
							VALUES (
								:WHMCSUserID,
								:OnAppUserID,
								:serverID,
								:invoiceID,
								":invoiceDate"
							)
							ON DUPLICATE KEY UPDATE
								`date` = ":invoiceDate"';
					$qry = str_replace( ':WHMCSUserID', $client[ 'client_id' ], $qry );
					$qry = str_replace( ':OnAppUserID', $client[ 'onapp_user_id' ], $qry );
					$qry = str_replace( ':serverID', $client[ 'server_id' ], $qry );
					$qry = str_replace( ':invoiceID', $result[ 'invoiceid' ], $qry );
					$qry = str_replace( ':invoiceDate', $clientAmount->date, $qry );

					full_query( $qry );
				}
			}
		}
	}

	private function generateInvoiceData( $data, array $client ) {
		global $_LANG;
		if( ! isset( $_LANG[ 'onappusersstatvirtualmachines' ] ) ) {
			eval( file_get_contents( dirname( dirname( __FILE__ ) ) . '/lang/English.txt' ) );
		}

		//check if invoice should be generated
		$fromTime = strtotime( $this->fromDate );
		$tillTime = strtotime( $data->date ) + $this->timeZoneOffset;
		if( $fromTime >= $tillTime ) {
			return false;
		}

		//check if the item should be taxed
		$taxed = empty( $client[ 'taxexempt' ] ) && (int)$client[ 'tax' ];
		if( $taxed ) {
			$taxrate = getTaxRate( 1, $client[ 'state' ], $client[ 'country' ] );
			$taxrate = $taxrate[ 'rate' ];
		}
		else {
			$taxrate = '';
		}

		$timeZone           = ' UTC' . ( $this->timeZoneOffset >= 0 ? '+' : '-' ) . ( $this->timeZoneOffset / 3600 );

		$this->fromDate = date( $_LANG[ 'onappusersstatdateformat' ], $fromTime );
		$this->tillDate = date( $_LANG[ 'onappusersstatdateformat' ], $tillTime );
		$this->tillDate = substr_replace( $this->tillDate, '59:59', - 5 );
		$invoiceDescription = array(
			$_LANG[ 'onappusersstatproduct' ] . $client[ 'packagename' ],
			$_LANG[ 'onappusersstatperiod' ] . $this->fromDate . ' - ' . $this->tillDate . $timeZone,
		);
		$invoiceDescription = implode( PHP_EOL, $invoiceDescription );

		return array(
			'userid'           => $client[ 'client_id' ],
			'date'             => $this->dueDate,
			'duedate'          => $this->dueDate,
			'paymentmethod'    => $client[ 'paymentmethod' ],
			'taxrate'          => $taxrate,
			'sendinvoice'      => true,

			'itemdescription1' => $invoiceDescription,
			'itemamount1'      => 0,
			'itemtaxed1'       => $taxed,

			'itemdescription2' => $_LANG[ 'onappusersstatvirtualmachines' ],
			'itemamount2'      => $data->vm,
			'itemtaxed2'       => $taxed,

			'itemdescription3' => $_LANG[ 'onappusersstatbackups' ],
			'itemamount3'      => $data->backup,
			'itemtaxed3'       => $taxed,

			'itemdescription4' => $_LANG[ 'onappusersstatmonitis' ],
			'itemamount4'      => $data->monitis,
			'itemtaxed4'       => $taxed,

			'itemdescription5' => $_LANG[ 'onappusersstattemplates' ],
			'itemamount5'      => $data->templates,
			'itemtaxed5'       => $taxed,

			'itemdescription6' => $_LANG[ 'onappusersstatstoragediskssize' ],
			'itemamount6'      => $data->storage,
			'itemtaxed6'       => $taxed,

			'itemdescription7' => $_LANG[ 'onappusersstatcdnedgegroup' ],
			'itemamount7'      => $data->edgecdn,
			'itemtaxed7'       => $taxed,
		);
	}

	private function getAmmount( array $user ) {
		$tmp = time();
		$this->timeZoneOffset = strtotime( date( 'Y-m-d H:i:s', $tmp ) ) - strtotime( gmdate( 'Y-m-d H:i:s', $tmp ) );

		if( $_SERVER[ 'argc' ] == 3 ) {
			if( $this->validateDate( $_SERVER[ 'argv' ][ 1 ] ) && $this->validateDate( $_SERVER[ 'argv' ][ 2 ] ) ) {
				$fromDate = $_SERVER[ 'argv' ][ 1 ];
				$tillDate = $_SERVER[ 'argv' ][ 2 ];

				$fromDateUTC = $this->getUTCTime( $fromDate );
				$tillDateUTC = $this->getUTCTime( $tillDate );
			}
		}
		else {
			$fromDate = $this->getLastInvoiceDate( $user );
			if( is_null( $fromDate ) ) {
				$fromDate = date( 'Y-m-01 00:00:00', strtotime( 'last month' ) );
				$tillDate = date( 'Y-m-t 23:59:59', strtotime( $fromDate ) );

				$fromDateUTC = $this->getUTCTime( $fromDate );
				$tillDateUTC = $this->getUTCTime( $tillDate, 'Y-m-t H:i:s' );
			}
			else {
				$fromDateUTC = $fromDate;
				$fromDate = date( 'Y-m-d H:00:00', strtotime( $fromDateUTC ) + $this->timeZoneOffset );
				$fromDate = date( 'Y-m-d H:00:00', strtotime( $fromDate . ' next hour' ) );
				$tillDate = date( 'Y-m-t 23:59:59', strtotime( $fromDate ) );
				$tillDateUTC = $this->getUTCTime( $tillDate, 'Y-m-t H:i:s' );
			}
		}
		$tillDateUTC = substr_replace( $tillDateUTC, '30', -5, 2 );
		$this->fromDate = $fromDate;

		$qry = 'SELECT
					SUM( `backup_cost` ) AS backup,
					SUM( `edge_group_cost` ) AS edgecdn,
					SUM( `monit_cost` ) AS monitis,
					SUM( `storage_disk_size_cost` ) AS storage,
					SUM( `template_cost` ) AS templates,
					SUM( `vm_cost` ) AS vm,
					SUM( `user_resources_cost` ) AS resources,
					SUM( `total_cost` ) AS total,
					MAX( `date` ) AS date
				FROM
					`onapp_itemized_resources`
				WHERE
					`whmcs_user_id` = :WHMCSUserID
					AND `onapp_user_id` = :OnAppUserID
					AND `server_id` = :serverID
					AND `date` BETWEEN ":dateFrom"
					AND ":dateTill"';
		$qry = str_replace( ':WHMCSUserID', $user[ 'client_id' ], $qry );
		$qry = str_replace( ':OnAppUserID', $user[ 'onapp_user_id' ], $qry );
		$qry = str_replace( ':serverID', $user[ 'server_id' ], $qry );
		$qry = str_replace( ':dateFrom', $fromDateUTC, $qry );
		$qry = str_replace( ':dateTill', $tillDateUTC, $qry );
		$data = mysql_fetch_assoc( full_query( $qry ) );
		return (object)$data;
	}

	private function getLastInvoiceDate( array $user ) {
		$qry = 'SELECT
					IFNULL( ADDTIME( MAX( `date` ), "00:00:01" ), NULL )
				FROM
					`onapp_itemized_invoices`
				WHERE
					`whmcs_user_id` = :WHMCSUserID
					AND `onapp_user_id` = :OnAppUserID
					AND `server_id` = :serverID';
		$qry = str_replace( ':WHMCSUserID', $user[ 'client_id' ], $qry );
		$qry = str_replace( ':OnAppUserID', $user[ 'onapp_user_id' ], $qry );
		$qry = str_replace( ':serverID', $user[ 'server_id' ], $qry );
		return mysql_result( mysql_query( $qry ), 0 );
	}

	private function getAdditionalFiles() {
		include_once $this->root . 'includes/processinvoices.php';
		include_once $this->root . 'includes/invoicefunctions.php';
		include_once $this->root . 'includes/tcpdf.php';
		include_once $this->root . 'includes/smarty/Smarty.class.php';
	}
}
new OnApp_UserModule_Cron_Invoices;