<?php

$root = dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . DIRECTORY_SEPARATOR;
require $root . 'dbconnect.php';
include $root . 'includes/functions.php';
include $root . 'includes/clientfunctions.php';
include $root . 'includes/modulefunctions.php';
include $root . 'includes/gatewayfunctions.php';
include $root . 'includes/ccfunctions.php';
include $root . 'includes/processinvoices.php';
include $root . 'includes/invoicefunctions.php';
include $root . 'includes/backupfunctions.php';
include $root . 'includes/ticketfunctions.php';
include $root . 'includes/currencyfunctions.php';
include $root . 'includes/tcpdf.php';
include $root . 'includes/smarty/Smarty.class.php';
@ini_set( 'memory_limit', '512M' );
@ini_set( 'max_execution_time', 0 );
@set_time_limit( 0 );

create_onappusers_invoice();

function create_onappusers_invoice() {
	global $root;
	require_once $root . 'modules/servers/onappusers/onappusers.php';

	$clients_query = 'SELECT
			tblonappusers.server_id,
			tblonappusers.client_id,
			tblonappusers.onapp_user_id,
			tblhosting.paymentmethod,
			tblhosting.domain,
			tblproducts.tax,
			tblclients.taxexempt,
			tblclients.state,
			tblclients.country,
			tblhosting.id AS service_id,
			tblproducts.name AS packagename
		FROM
			tblonappusers
			LEFT JOIN tblhosting ON
				tblhosting.userid = tblonappusers.client_id
				AND tblhosting.server = tblonappusers.server_id
			LEFT JOIN tblproducts ON
				tblhosting.packageid = tblproducts.id
				AND tblproducts.servertype = "onappusers"
			LEFT JOIN tblclients ON
				tblclients.id = tblonappusers.client_id
		WHERE
			tblhosting.domainstatus IN ( "Active", "Suspended" )
			AND tblproducts.name IS NOT NULL';
	$clients_result = full_query( $clients_query );

	$servers_query = 'SELECT
			id,
			ipaddress,
			username,
			password
		FROM
			tblservers
		WHERE
			type = "onappusers"';
	$servers_result = full_query( $servers_query );
	$servers = array();
	while( $server = mysql_fetch_assoc( $servers_result ) ) {
		$server[ 'password' ] = decrypt( $server[ 'password' ] );
		$servers[ $server[ 'id' ] ] = $server;
	}

	//get admin
	$sql = 'SELECT `username` FROM `tbladmins` LIMIT 1';
	$res = mysql_query( $sql );
	$admin = mysql_result( $res, 0 );
	//calculate invoice dates
	$date = $duedate = date( 'Ymd' );
	while( $client = mysql_fetch_assoc( $clients_result ) ) {
		$onapp_user = get_onapp_object(
			'OnApp_User',
			$servers[ $client[ 'server_id' ] ][ 'ipaddress' ],
			$servers[ $client[ 'server_id' ] ][ 'username' ],
			$servers[ $client[ 'server_id' ] ][ 'password' ]
		);
		$onapp_user->_id = $client[ 'onapp_user_id' ];
		$onapp_user->load();

		$client_amount_query = 'SELECT
				tblinvoices.subtotal AS amount
			FROM tblinvoices
			WHERE
				tblinvoices.userid = ' . $client[ 'client_id' ] . '
				AND tblinvoices.status = "Unpaid"';
		$client_amount = 0;
		$client_amount_result = full_query( $client_amount_query );
		while( $row = mysql_fetch_assoc( $client_amount_result ) ) {
			$client_amount += $row[ 'amount' ];
		}

		$amount_diff = $onapp_user->_obj->_outstanding_amount - $client_amount;
		$amount_diff = round( $amount_diff, 2 );

		if( $amount_diff > 0 ) {
			//get if the item should be taxed and tax rate
			$taxed = empty( $client[ 'taxexempt' ] ) && (int)$client[ 'tax' ];
			if( $taxed ) {
				$taxrate = getTaxRate( 1, $client[ 'state' ], $client[ 'country' ] );
				$taxrate = $taxrate[ 'rate' ];
			}
			else {
				$taxrate = '';
			}

			$data = array(
				'userid' => $client[ 'client_id' ],
				'date' => $date,
				'duedate' => $duedate,
				'paymentmethod' => $client[ 'paymentmethod' ],
				'taxrate' => $taxrate,
				'sendinvoice' => true,

				'itemdescription1' => $client[ 'domain' ] . ' ' . $client[ 'packagename' ],
				'itemamount1' => $amount_diff,
				'itemtaxed1' => $taxed
			);
			$result = localAPI( 'CreateInvoice', $data, $admin );

			if( $result[ 'result' ] != 'success' ) {
				echo 'Following error occurred: ' . $result[ 'result' ] . PHP_EOL;
			}
			else {
				$sql = 'UPDATE tblinvoiceitems SET relid = ' . $client[ 'service_id' ] . ', type = "onappusers" WHERE invoiceid = ' . $result[ 'invoiceid' ];
				full_query( $sql );
			}
		}
	}
}