<?php

require dirname( __FILE__ ) . '/onapp.cron.php';
class OnApp_UserModule_Cron_Invoices_Test extends OnApp_UserModule_Cron {
	private $fromDate, $tillDate, $timeZoneOffset, $dueDate;
	private $logFile, $logText = array();

	protected function run() {
		$this->logFile = dirname( __FILE__ ) . '/onapp_invoices_test.txt';
		$this->getAdditionalFiles();
		$this->process();
	}

	private function process() {
		//get admin
		$sql   = 'SELECT
					`username`
				FROM
					`tbladmins`
				LIMIT 1';
		$res   = mysql_query( $sql );
		$admin = mysql_result( $res, 0 );
		//calculate invoice due date
		$this->dueDate = date( 'Ymd' );

		while( $client = mysql_fetch_assoc( $this->clients ) ) {
			$clientAmount = $this->getAmmount( $client );
			if( ! is_null( $clientAmount->total ) ) {
				$data = $this->generateInvoiceData( $clientAmount, $client );

				$tmp = '';
				$tmp .= 'WHMCS user ID: ' . $client[ 'client_id' ] . PHP_EOL;
				$tmp .= 'OnApp user ID: ' . $client[ 'onapp_user_id' ] . PHP_EOL;
				$tmp .= 'Server ID: ' . $client[ 'server_id' ] . PHP_EOL . PHP_EOL;
				$tmp .= $data[ 'itemdescription1' ] . PHP_EOL;
				$tmp .= 'Total: ' . $data[ 'itemamount1' ] . PHP_EOL;
				$tmp .= 'item will be taxed: ' . $data[ 'taxrate' ] . '%' . PHP_EOL;
				$this->logText[] = $tmp;
			}
		}
		$this->writeLog();
	}

	private function generateInvoiceData( $data, array $client ) {
		global $_LANG;
		//check if the item should be taxed
		$taxed = empty( $client[ 'taxexempt' ] ) && (int)$client[ 'tax' ];
		if( $taxed ) {
			$taxrate = getTaxRate( 1, $client[ 'state' ], $client[ 'country' ] );
			$taxrate = $taxrate[ 'rate' ];
		}
		else {
			$taxrate = '';
		}

		$invoiceCurrency = getCurrency( $client[ 'client_id' ] );
		$invoiceCurrency = $invoiceCurrency[ 'prefix' ];

		$timeZone           = ' UTC' . ( $this->timeZoneOffset >= 0 ? '+' : '-' ) . ( $this->timeZoneOffset / 3600 );
		$this->fromDate     = date( $_LANG[ 'onappusersstatdateformathours' ], strtotime( $this->fromDate ) );
		$this->tillDate     = date( $_LANG[ 'onappusersstatdateformathours' ], strtotime( $data->date ) + $this->timeZoneOffset );
		$invoiceDescription = array(
			$_LANG[ 'onappusersstatproduct' ] . ': ' . $client[ 'packagename' ],
			$_LANG[ 'onappusersstatpperiod' ] . ': ' . $this->fromDate . ' - ' . $this->tillDate . $timeZone,
			$_LANG[ 'onappusersstatbackups' ] . ': ' . $invoiceCurrency . number_format( $data->backup, 4, '.', ' ' ),
			$_LANG[ 'onappusersstatmonitis' ] . ': ' . $invoiceCurrency . number_format( $data->monitis, 4, '.', ' ' ),
			$_LANG[ 'onappusersstattemplates' ] . ': ' . $invoiceCurrency . number_format( $data->templates, 4, '.', '' ),
			$_LANG[ 'onappusersstatstoragediskssize' ] . ': ' . $invoiceCurrency . number_format( $data->storage, 4, '.', ' ' ),
			$_LANG[ 'onappusersstatcdnedgegroup' ] . ': ' . $invoiceCurrency . number_format( $data->edgecdn, 4, '.', ' ' ),
			$_LANG[ 'onappusersstatvirtualmachines' ] . ': ' . $invoiceCurrency . number_format( $data->vm, 4, '.', ' ' ),
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
			'itemamount1'      => number_format( $data->total, 4, '.', '' ),
			'itemtaxed1'       => $taxed,

		);
	}

	private function getAmmount( array $user ) {
		$tmp                  = time();
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
				$fromDate    = date( 'Y-m-d H:00:00', strtotime( $fromDateUTC ) + $this->timeZoneOffset );
				$fromDate    = date( 'Y-m-d H:00:00', strtotime( $fromDate . ' next hour' ) );
				$tillDate    = date( 'Y-m-t 23:59:59', strtotime( $fromDate ) );
				$tillDateUTC = $this->getUTCTime( $tillDate, 'Y-m-t H:i:s' );
			}
		}
		$tillDateUTC    = substr_replace( $tillDateUTC, '30', - 5, 2 );
		$this->fromDate = $fromDate;
		$this->tillDate = $tillDate;

		$qry  = 'SELECT
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
					`whmcs_user_id` = ' . $user[ 'client_id' ] . '
					AND `onapp_user_id` = ' . $user[ 'onapp_user_id' ] . '
					AND `server_id` = ' . $user[ 'server_id' ] . '
					AND `date` BETWEEN "' . $fromDateUTC . '"
					AND "' . $tillDateUTC . '"';
		$data = mysql_fetch_assoc( full_query( $qry ) );
		return (object)$data;
	}

	private function getLastInvoiceDate( array $user ) {
		$qry = 'SELECT
					MAX( `date` )
				FROM
					`onapp_itemized_invoices`
				WHERE
					`whmcs_user_id` = ' . $user[ 'client_id' ] . '
					AND `onapp_user_id` = ' . $user[ 'onapp_user_id' ] . '
					AND `server_id` = ' . $user[ 'server_id' ];
		return mysql_result( mysql_query( $qry ), 0 );
	}

	private function getAdditionalFiles() {
		include_once $this->root . 'includes/processinvoices.php';
		include_once $this->root . 'includes/invoicefunctions.php';
	}

	private function writeLog() {
		if( ! ( file_exists( $this->logFile ) && is_writable( $this->logFile ) ) ) {
			if( ! $log = fopen( $this->logFile, 'w' ) ) {
				exit( 'Can\'t write log file. Check if file ' . $this->logFile . ' exists and is writable!' . PHP_EOL );
			}
			else {
				fclose( $log );
			}
		}

		$splitter = PHP_EOL . str_repeat( '=', 60 ) . PHP_EOL . PHP_EOL;
		$log = implode( $splitter, $this->logText );
		file_put_contents( $this->logFile, $log );
		echo 'Open file {WHMCS}/modules/servers/onappusers/cronjobs/onapp_invoices_test.txt in browser!', PHP_EOL;
	}
}
new OnApp_UserModule_Cron_Invoices_Test;