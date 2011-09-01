<?php

if( !function_exists( 'onappusers_ConfigOptions' ) ) {
	require_once ROOTDIR . '/modules/servers/onappusers/onappusers.php';
}

function onappusers_autosuspend() {
	global $CONFIG;

	if( $CONFIG[ 'AutoSuspension' ] != 'on' ) {
		return;
	}

	$suspenddate = date( 'Y-m-d' );
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
		RIGHT JOIN tblinvoiceitems ON
				tblinvoiceitems.invoiceid = tblinvoices.id
				AND tblinvoiceitems.relid = tblhosting.id
		LEFT JOIN tblproducts ON
				tblproducts.id = tblhosting.packageid
		WHERE
			tblinvoices.id = ' . $invoice_id . '
			AND tblinvoices.status = "Paid"
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

	$client_amount_query = "SELECT
			SUM(tblinvoices.subtotal) AS amount
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
		$params[ 'serverip' ] = $server[ 'ipaddress' ];
		$params[ 'serverusername' ] = $server[ 'username' ];
		$params[ 'serverpassword' ] = $server[ 'password' ];

		if( onappusers_UnsuspendAccount( $params ) == 'success' ) {
			update_query( 'tblhosting', array( 'domainstatus' => 'Active' ), array( 'id' => $client[ 'service_id' ] ) );
		}
	}
}

add_hook( 'DailyCronJob', 1, 'onappusers_autosuspend' );
add_hook( 'InvoicePaid', 1, 'onappusers_invoice_paid' );