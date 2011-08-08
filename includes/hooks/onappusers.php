<?php

if( !function_exists( 'onappusers_ConfigOptions' ) ) {
	require_once ROOTDIR . '/modules/servers/onappusers/onappusers.php';
}

//todo check this function
function autosuspend_onappusers() {
	global $CONFIG;

	if( $CONFIG[ 'AutoSuspension' ] != 'on' ) {
		return;
	}

	$suspenddate = date( 'Ymd', mktime( 0, 0, 0, date( 'm' ), date( 'd' ) - $CONFIG[ 'AutoSuspensionDays' ], date( 'Y' ) ) );
	$to_suspend_query = "SELECT tblhosting.id
		FROM
			tblinvoices
			LEFT JOIN tblinvoiceitems ON
				tblinvoiceitems.invoiceid = tblinvoices.id
			LEFT JOIN tblhosting ON
				tblhosting.id = tblinvoiceitems.relid
		WHERE
			tblinvoices.status = 'Unpaid'
			AND tblinvoiceitems.type = 'onappusers'
			AND tblhosting.domainstatus = 'Active'
			AND tblinvoices.duedate <= $suspenddate
			AND tblhosting.overideautosuspend != 'on'";
	$to_suspend_result = full_query( $to_suspend_query );

	while( $to_suspend = mysql_fetch_assoc( $to_suspend_result ) ) {
		serversuspendaccount( $to_suspend[ 'id' ] );
	}
}

//todo check this function
function unsuspend_user( $vars ) {
	$invoice_id = $vars[ 'invoiceid' ];
	$client_query = "SELECT
			tblonappusers.client_id,
			tblonappusers.server_id,
			tblonappusers.onapp_user_id,
			tblhosting.id AS service_id,
			SUM(tblinvoiceitems.amount) AS amount
		FROM
			tblinvoices
			LEFT JOIN tblinvoiceitems ON
				tblinvoiceitems.invoiceid = tblinvoices.id
			LEFT JOIN tblonappusers ON
				tblinvoiceitems.userid = tblonappusers.client_id
			LEFT JOIN tblhosting ON
				tblhosting.userid = tblonappusers.client_id
				AND tblhosting.server = tblonappusers.server_id
		WHERE
			tblinvoices.id = $invoice_id
			AND tblinvoiceitems.type = 'onappusers'
		GROUP BY tblinvoices.id";
	$client_result = full_query( $client_query );
	$client = mysql_fetch_assoc( $client_result );

	if( !$client ) {
		return;
	}

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

	$onapp_payment = get_onapp_object(
		'ONAPP_Payment',
		$servers[ $client[ 'server_id' ] ][ 'ipaddress' ],
		$servers[ $client[ 'server_id' ] ][ 'username' ],
		$servers[ $client[ 'server_id' ] ][ 'password' ]
	);

	$onapp_payment->_user_id = $client[ 'onapp_user_id' ];
	$onapp_payment->_amount = $client[ 'amount' ];
	$onapp_payment->_invoice_number = $invoice_id;

	$onapp_payment->save();

	$client_amount_query = "SELECT
			SUM(tblinvoiceitems.amount) AS amount
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
	$client_amount = mysql_fetch_assoc( $client_amount_result );

	if( !$client_amount[ 'amount' ] ) {
		$params[ 'serviceid' ] = $client[ 'service_id' ];
		$params[ 'clientsdetails' ][ 'userid' ] = $client[ 'client_id' ];
		$params[ 'serverid' ] = $client[ 'server_id' ];
		$params[ 'serverip' ] = $servers[ $client[ 'server_id' ] ][ 'ipaddress' ];
		$params[ 'serverusername' ] = $servers[ $client[ 'server_id' ] ][ 'username' ];
		$params[ 'serverpassword' ] = $servers[ $client[ 'server_id' ] ][ 'password' ];

		if( onappusers_UnsuspendAccount( $params ) == 'success' ) {
			update_query( 'tblhosting', array( 'domainstatus' => 'Active' ), array( 'id' => $client[ 'service_id' ] ) );
		}
	}
}

function onappusers_invoice_paid( $vars ) {
	$invoice_id = $vars[ 'invoiceid' ];
	$client_query = 'SELECT
			tblonappusers.client_id,
			tblonappusers.server_id,
			tblonappusers.onapp_user_id,
			tblhosting.id AS service_id,
			tblinvoices.subtotal AS amount
		FROM
			tblinvoices
		LEFT JOIN tblonappusers ON
				tblinvoices.userid = tblonappusers.client_id
		LEFT JOIN tblhosting ON
				tblhosting.userid = tblonappusers.client_id
				AND tblhosting.server = tblonappusers.server_id
		LEFT JOIN tblinvoiceitems ON
				tblinvoiceitems.invoiceid = tblinvoices.id
				AND tblinvoiceitems.relid = tblhosting.id
		LEFT JOIN tblproducts ON
				tblproducts.id = tblhosting.packageid
		WHERE
			tblinvoices.id = ' . $invoice_id . '
			AND tblinvoices.status = "Unpaid"
			AND tblproducts.servertype = "onappusers"
		GROUP BY tblinvoices.id';
	$client_result = full_query( $client_query );
	$client = mysql_fetch_assoc( $client_result );

	if( !$client || ( $client[ 'amount'] == 0 ) ) {
		return;
	}

	$servers_query = 'SELECT
			id,
			ipaddress,
			username,
			password
		FROM
			tblservers
		WHERE
			type = "onappusers" AND id = ' . $client[ 'server_id' ];
	$servers_result = full_query( $servers_query );
	while( $server = mysql_fetch_assoc( $servers_result ) ) {
		$server[ 'password' ] = decrypt( $server[ 'password' ] );
		break;
	}

	$onapp_payment = get_onapp_object(
		'OnApp_Payment',
		$server[ 'ipaddress' ],
		$server[ 'username' ],
		$server[ 'password' ]
	);

	$onapp_payment->_user_id = $client[ 'onapp_user_id' ];
	$onapp_payment->_amount = $client[ 'amount' ];
	$onapp_payment->_invoice_number = $invoice_id;

	$onapp_payment->save();
	$error = $onapp_payment->getErrorsAsString();
	if( !empty( $error ) ) {
		echo 'ERROR with OnApp payment: ' . $error;
	}
}

add_hook( 'DailyCronJob', 1, 'autosuspend_onappusers' );
add_hook( 'InvoicePaid', 1, 'onappusers_invoice_paid' );