<?php

function hook_onappusers_invoice_paid( $vars ) {
	if( ! defined( 'ONAPP_WRAPPER_INIT' ) ) {
		$path = dirname( dirname( __FILE__ ) );
		define( 'ONAPP_WRAPPER_INIT', $path . '/wrapper/OnAppInit.php' );
		require_once ONAPP_WRAPPER_INIT;
		require_once $path . '/modulefunctions.php';
	}

	$invoice_id = $vars[ 'invoiceid' ];
	$qry  = 'SELECT
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
			AND tblinvoiceitems.type = "onappusers"
		GROUP BY tblinvoices.id';
	$result = full_query( $qry );

	if( mysql_num_rows( $result ) == 0 ) {
		return;
	}

	$data = mysql_fetch_assoc( $result );

	$qry  = 'SELECT
			`ipaddress` AS serverip,
			`username` AS serverusername,
			`password` AS serverpassword
		FROM
			`tblservers`
		WHERE
			`type` = "onappusers"
			AND `id` = ' . $data[ 'server_id' ];
	$result = full_query( $qry );
	$server = mysql_fetch_assoc( $result );
	$server[ 'serverpassword' ] = decrypt( $server[ 'serverpassword' ] );

	$module = new OnApp_UserModule( $server );
	$payment = $module->getOnAppObject( 'OnApp_Payment' );
	$payment->_user_id        = $data[ 'onapp_user_id' ];
	$payment->_amount         = $data[ 'amount' ];
	$payment->_invoice_number = $invoice_id;
	$payment->save();

	$error = $payment->getErrorsAsString();
	if( empty( $error ) ) {
		logactivity( 'OnApp payment was sent. Service ID #' . $data[ 'service_id' ] . ', amount: ' . $data[ 'amount' ] );
	}
	else {
		logactivity( 'ERROR with OnApp payment for service ID #' . $data[ 'service_id' ] . ': ' . $error );
	}

	// check for other unpaid invoices for this service
	$qry = 'SELECT
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
			tblinvoiceitems.relid = ' . $data[ 'service_id' ] . '
			AND tblinvoices.status = "Unpaid"
			AND tblproducts.servertype = "onappusers"
			AND tblinvoiceitems.type = "onappusers"
		GROUP BY tblinvoices.id';
	$result = full_query( $qry );

	if( mysql_num_rows( $result ) == 0 ) {
		serverunsuspendaccount( $data[ 'service_id' ] );
	}
}

function hook_onappusers_autosuspend() {
	global $CONFIG;

	if( $CONFIG[ 'AutoSuspension' ] != 'on' ) {
		return;
	}

	$qry = 'SELECT tblhosting.id
		FROM
			tblinvoices
		LEFT JOIN tblinvoiceitems ON
				tblinvoiceitems.invoiceid = tblinvoices.id
		LEFT JOIN tblhosting ON
				tblhosting.id = tblinvoiceitems.relid
		WHERE
			tblinvoices.status = "Unpaid"
			AND tblinvoiceitems.type = "onappusers"
			AND tblhosting.domainstatus = "Active"
			AND tblinvoices.duedate <= DATE_ADD( `tblinvoices`.duedate, INTERVAL ' . $CONFIG[ 'AutoSuspensionDays' ] . ' DAY )
			AND tblhosting.overideautosuspend != "on"
		GROUP BY
			tblhosting.id';
	$result = full_query( $qry );

	while( $data = mysql_fetch_assoc( $result ) ) {
		serversuspendaccount( $data[ 'id' ] );
	}
}

function hook_onappusers_autoterminate() {
	global $CONFIG;

	if( $CONFIG[ 'AutoTermination' ] != 'on' ) {
		return;
	}

	$qry = 'SELECT tblhosting.id
		FROM
			tblinvoices
		LEFT JOIN tblinvoiceitems ON
				tblinvoiceitems.invoiceid = tblinvoices.id
		LEFT JOIN tblhosting ON
				tblhosting.id = tblinvoiceitems.relid
		WHERE
			tblinvoices.status = "Unpaid"
			AND tblinvoiceitems.type = "onappusers"
			AND tblhosting.domainstatus = "Suspended"
			AND tblinvoices.duedate <= DATE_ADD( `tblinvoices`.duedate, INTERVAL ' . $CONFIG[ 'AutoTerminationDays' ] . ' DAY )
			AND tblhosting.overideautosuspend != "on"
		GROUP BY
			tblhosting.id';
	$result = full_query( $qry );

	while( $data = mysql_fetch_assoc( $result ) ) {
		serverterminateaccount( $data[ 'id' ] );
	}
}

add_hook( 'DailyCronJob', 1, 'hook_onappusers_autosuspend' );
add_hook( 'DailyCronJob', 2, 'hook_onappusers_autoterminate' );
add_hook( 'InvoicePaid', 1, 'hook_onappusers_invoice_paid' );