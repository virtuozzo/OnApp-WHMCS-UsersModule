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
@ini_set( 'memory_limit', '512M' );
@ini_set( 'max_execution_time', 0 );
@set_time_limit( 0 );

create_onappusers_invoice();

function create_onappusers_invoice() {
	global $root;
	require_once $root . 'modules/servers/onappusers/onappusers.php';

	$clients_query = "SELECT
			tblonappusers.server_id,
			tblonappusers.client_id,
			tblonappusers.onapp_user_id,
			tblhosting.id AS service_id
		FROM
			tblonappusers
			LEFT JOIN tblhosting ON
				tblhosting.userid = tblonappusers.client_id
				AND tblhosting.server = tblonappusers.server_id
			LEFT JOIN tblproducts ON
				tblhosting.packageid = tblproducts.id
				AND tblproducts.servertype = 'onappusers'
		WHERE
			tblhosting.domainstatus = 'Active'
		GROUP BY tblonappusers.client_id, tblonappusers.server_id";
	$clients_result = full_query( $clients_query );

	$servers_query = "SELECT
			id,
			ipaddress,
			username,
			password
		FROM
			tblservers
		WHERE
			type = 'onappusers'";
	$servers_result = full_query( $servers_query );
	$servers = array();
	while( $server = mysql_fetch_assoc( $servers_result ) ) {
		$server[ 'password' ] = decrypt( $server[ 'password' ] );
		$servers[ $server[ 'id' ] ] = $server;
	}

	while( $client = mysql_fetch_assoc( $clients_result ) ) {
		$onapp_user = get_onapp_object(
			'OnApp_User',
			$servers[ $client[ 'server_id' ] ][ 'ipaddress' ],
			$servers[ $client[ 'server_id' ] ][ 'username' ],
			$servers[ $client[ 'server_id' ] ][ 'password' ]
		);
		$onapp_user->_id = $client[ 'onapp_user_id' ];
		$onapp_user->load();

		$client_amount_query = "SELECT
				SUM(tblinvoiceitems.amount) AS amount,
				tblinvoiceitems.amount AS qq
			FROM
				tblinvoices
				LEFT JOIN tblinvoiceitems ON tblinvoiceitems.invoiceid = tblinvoices.id
				LEFT JOIN tblonappusers ON tblinvoiceitems.userid = tblonappusers.client_id
			WHERE
				tblonappusers.client_id = " . $client[ 'client_id' ] . "
				AND tblinvoiceitems.type = 'onappusers'
				AND tblinvoices.status = 'Unpaid'
			GROUP BY tblonappusers.client_id, tblonappusers.server_id";
		$client_amount_result = full_query( $client_amount_query );
		$client_amount_array = mysql_fetch_assoc( $client_amount_result );
		$client_amount = $client_amount_array ? $client_amount_array[ 'amount' ] : 0;

		$amount_diff = $onapp_user->_obj->_outstanding_amount - $client_amount;
		$amount_diff = round( $amount_diff, 2 );

		if( $amount_diff > 0 ) {
			$invoice_date = date( 'Y-m-d' );
			$invoice_id = insert_query( 'tblinvoices', array(
					'userid' => $client[ 'client_id' ],
					'date' => $invoice_date,
					'duedate' => $invoice_date,
					'subtotal' => $amount_diff,
					'total' => $amount_diff,
					'status' => 'Unpaid'
				)
			);
			insert_query( 'tblinvoiceitems', array(
					'invoiceid' => $invoice_id,
					'userid' => $client[ 'client_id' ],
					'type' => 'onappusers',
					'relid' => $client[ 'service_id' ],
					'amount' => $amount_diff,
					'description' => 'OnApp Usage'
				)
			);
		}
	}
}