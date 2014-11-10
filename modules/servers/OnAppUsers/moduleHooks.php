<?php

if( ! defined( 'WHMCS' ) ) {
	exit( 'This file cannot be accessed directly' );
}

function OnAppUsers_ProductEdit_Hook( $vars ) {
	if( $vars[ 'servertype' ] === 'OnAppUsers' ) {
		# create custom field
		$fields = array(
			'relid'     => $vars[ 'pid' ],
			'type'      => 'product',
			'fieldname' => 'OnApp user ID',
			'fieldtype' => 'text',
			'adminonly' => 'on',
			'required'  => 'on',
		);
		if( ! mysql_num_rows( select_query( 'tblcustomfields', 'id', $fields ) ) ) {
			insert_query( 'tblcustomfields', $fields );
		}

		# configoption2 = SuspendDays
		# configoption3 = TerminateDays
		$opts   = json_decode( htmlspecialchars_decode( $vars[ 'configoption1' ] ) );
		$table  = 'tblproducts';
		$update = array(
			'configoption2' => current( $opts->SuspendDays ),
			'configoption3' => current( $opts->TerminateDays ),
		);
		$where  = array( 'id' => $vars[ 'pid' ] );
		update_query( $table, $update, $where );
	}

	return true;
}

function OnAppUsers_InvoicePaid_Hook( $vars ) {
	$invoiceID = $vars[ 'invoiceid' ];
	$qry = 'SELECT
				tblinvoices.`subtotal` AS subtotal,
				tblinvoices.`total` AS total,
				tblcustomfieldsvalues.`value` AS OnAppUserID,
				tblservers.`ipaddress`,
				tblservers.`hostname`,
				tblservers.`username`,
				tblservers.`password`,
				tblservers.`secure`,
				tblhosting.`server` AS serverID,
                tblproducts.`configoption1` AS settings,
                tblhosting.`domainstatus` AS `status`,
				tblhosting.`id` AS serviceID
			FROM
				tblinvoices
			JOIN
				tblinvoiceitems ON
				tblinvoices.id = tblinvoiceitems.invoiceid
			JOIN
				tblhosting ON
				tblhosting.id = tblinvoiceitems.relid
			JOIN
				tblservers ON
				tblservers.id = tblhosting.server
			JOIN
				tblcustomfields ON
				tblcustomfields.`relid` = tblhosting.`packageid`
				AND tblcustomfields.`fieldname` = BINARY "OnApp user ID"
			JOIN
				tblcustomfieldsvalues ON
				tblcustomfieldsvalues.`fieldid` = tblcustomfields.`id`
			JOIN
				tblproducts ON
                tblproducts.`id` = tblhosting.`packageid`
			WHERE
				tblinvoices.`status` = "Paid"
				AND tblinvoiceitems.`type` = BINARY "OnAppUsers"
				AND tblinvoices.id = :invoiceID
			GROUP BY
				tblinvoices.`userid`';
	$qry = str_replace( ':invoiceID', $invoiceID, $qry );
	$result = full_query( $qry );

	if( mysql_num_rows( $result ) == 0 ) {
		return;
	}
	$data = mysql_fetch_object( $result );

	$data->password = decrypt( $data->password );
	$productSettings = json_decode( htmlspecialchars_decode( $data->settings ) );

	if( $productSettings->PassTaxes->{$data->serverID} == 0 ) {
		$amount = $data->subtotal;
	}
	else {
		$amount = $data->total;
	}

	if( $data->secure == 'on' ) {
		$serverAddr = 'https://';
	}
	else {
		$serverAddr = 'http://';
	}
	$serverAddr .= empty( $data->hostname ) ? $data->ipaddress : $data->hostname;

	$path = dirname( dirname( dirname( __DIR__ ) ) ) . '/includes/';
	if( ! defined( 'ONAPP_WRAPPER_INIT' ) ) {
		define( 'ONAPP_WRAPPER_INIT', $path . 'wrapper/OnAppInit.php' );
		require_once ONAPP_WRAPPER_INIT;
	}

	$payment = new OnApp_Payment;
	$payment->auth( $serverAddr, $data->username, $data->password );
	$payment->_user_id = $data->OnAppUserID;
	$payment->_amount = $amount;
	$payment->_invoice_number = $invoiceID;
	$payment->save();

	$error = $payment->getErrorsAsString();
	if( empty( $error ) ) {
		logactivity( 'OnApp payment was sent. Service ID #' . $data->serviceID . ', amount: ' . $amount );
	}
	else {
		logactivity( 'ERROR with OnApp payment for service ID #' . $data->serviceID . ': ' . $error );
	}

	if( $data->status == 'Suspended' ) {
		# check for other unpaid invoices for this service
		$qry = 'SELECT
					tblinvoices.`id`
				FROM
					tblinvoices
				RIGHT JOIN tblinvoiceitems ON
					tblinvoiceitems.`invoiceid` = tblinvoices.`id`
					AND tblinvoiceitems.`relid` = :serviceID
				WHERE
					tblinvoices.`status` = "Unpaid"
				GROUP BY
					tblinvoices.`id`';
		$qry = str_replace( ':serviceID', $data->serviceID, $qry );
		$result = full_query( $qry );

		if( mysql_num_rows( $result ) == 0 ) {
			if( ! function_exists( 'ServerUnsuspendAccount' ) ) {
				require_once $path . 'modulefunctions.php';
			}
			ServerUnsuspendAccount( $data->serviceID );
		}
	}
}

function OnAppUsers_AutoSuspend_Hook() {
	global $CONFIG, $cron;

	if( $CONFIG[ 'AutoSuspension' ] != 'on' ) {
		return;
	}

	$qry = 'SELECT
				tblhosting.`id`
			FROM
				tblinvoices
			LEFT JOIN
				tblinvoiceitems ON
				tblinvoiceitems.`invoiceid` = tblinvoices.`id`
			LEFT JOIN
				tblhosting ON
				tblhosting.`id` = tblinvoiceitems.`relid`
			LEFT JOIN
				tblproducts ON
				tblproducts.`id` = tblhosting.`packageid`
			WHERE
				tblinvoices.`status` = "Unpaid"
				AND tblinvoiceitems.`type` = BINARY "OnAppUsers"
				AND tblhosting.`domainstatus` = "Active"
				AND NOW() > DATE_ADD( tblinvoices.`duedate`, INTERVAL tblproducts.`configoption2` DAY )
				AND (tblhosting.`overideautosuspend` NOT IN ( "on", 1 )
				OR tblhosting.`overidesuspenduntil` <= NOW() )
			GROUP BY
				tblhosting.`id`';
	$result = full_query( $qry );

	$cnt = 0;
	echo 'Starting Processing OnApp Suspensions', PHP_EOL;
	while( $data = mysql_fetch_assoc( $result ) ) {
		ServerSuspendAccount( $data[ 'id' ] );
		echo ' - suspend service #', $data[ 'id' ], PHP_EOL;
		++$cnt;
	}
	echo ' - Processed ', $cnt, ' Suspensions', PHP_EOL;
	$cron->emailLog( $cnt . ' OnApp Services Suspended' );
}

function OnAppUsers_AutoTerminate_Hook() {
	global $CONFIG, $cron;

	if( $CONFIG[ 'AutoTermination' ] != 'on' ) {
		return;
	}

	$qry = 'SELECT
				tblhosting.`id`
			FROM
				tblinvoices
			LEFT JOIN tblinvoiceitems ON
				tblinvoiceitems.`invoiceid` = tblinvoices.`id`
			LEFT JOIN tblhosting ON
				tblhosting.`id` = tblinvoiceitems.`relid`
			LEFT JOIN
				tblproducts ON
				tblproducts.`id` = tblhosting.`packageid`
			WHERE
				tblinvoices.`status` = "Unpaid"
				AND tblinvoiceitems.`type` = BINARY "OnAppUsers"
				AND tblhosting.`domainstatus` = "Suspended"
				AND NOW() > DATE_ADD( tblinvoices.`duedate`, INTERVAL tblproducts.`configoption3` DAY )
			GROUP BY
				tblhosting.`id`';
	$result = full_query( $qry );

	$cnt = 0;
	echo 'Starting Processing OnApp Terminations', PHP_EOL;
	while( $data = mysql_fetch_assoc( $result ) ) {
		ServerTerminateAccount( $data[ 'id' ] );
		echo ' - terminate service #', $data[ 'id' ], PHP_EOL;
		++ $cnt;
	}
	echo ' - Processed ', $cnt, ' Terminations', PHP_EOL;
	$cron->emailLog( $cnt . ' OnApp Services Terminated' );
}

add_hook( 'InvoicePaid', 1, 'OnAppUsers_InvoicePaid_Hook' );
add_hook( 'ProductEdit', 1, 'OnAppUsers_ProductEdit_Hook' );
add_hook( 'PreCronJob', 1, 'OnAppUsers_AutoSuspend_Hook' );
add_hook( 'PreCronJob', 2, 'OnAppUsers_AutoTerminate_Hook' );
