<?php

$OnAppUsersProducts = array();

if( ! defined( 'WHMCS' ) ) {
    exit( 'This file cannot be accessed directly' );
}

function OnAppUsers_ProductEdit_Hook( $vars ) {
    if( $vars[ 'servertype' ] === 'OnAppUsers' ) {
        // create custom field
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

        // configoption2 = SuspendDays
        // configoption3 = TerminateDays
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
    mail( 'devman@localhost', __FUNCTION__, print_r( $vars, true ) );
    return;

    $invoice_id = $vars[ 'invoiceid' ];
    $qry = 'SELECT
                tblonappusers.`client_id`,
                tblonappusers.`server_id`,
                tblonappusers.`onapp_user_id`,
                tblhosting.`id` AS service_id,
                tblinvoices.`subtotal` AS subtotal,
                tblinvoices.`total` AS total,
                tblproducts.`configoption1` AS settings,
                tblhosting.`domainstatus` AS status
            FROM
                tblinvoices
            LEFT JOIN tblonappusers ON
                tblinvoices.`userid` = tblonappusers.`client_id`
            LEFT JOIN tblhosting ON
                tblhosting.`userid` = tblonappusers.`client_id`
                AND tblhosting.`server` = tblonappusers.`server_id`
            RIGHT JOIN tblinvoiceitems ON
                tblinvoiceitems.`invoiceid` = tblinvoices.`id`
                AND tblinvoiceitems.`relid` = tblhosting.`id`
            LEFT JOIN tblproducts ON
                tblproducts.`id` = tblhosting.`packageid`
            WHERE
                tblinvoices.`id` = :invoiceID
                AND tblinvoices.`status` = "Paid"
                AND tblproducts.`servertype` = "onappusers"
                AND tblinvoiceitems.`type` = "onappusers"
            GROUP BY tblinvoices.`id`';
    $qry = str_replace( ':invoiceID', $invoice_id, $qry );
    $result = full_query( $qry );

    if( mysql_num_rows( $result ) == 0 ) {
        return;
    }

    $data = mysql_fetch_assoc( $result );

    $qry = 'SELECT
                `ipaddress` AS serverip,
                `username` AS serverusername,
                `password` AS serverpassword
            FROM
                tblservers
            WHERE
                `type` = "onappusers"
                AND `id` = :serverID';
    $qry = str_replace( ':serverID', $data[ 'server_id' ], $qry );
    $result = full_query( $qry );
    $server = mysql_fetch_assoc( $result );
    $server[ 'serverpassword' ] = decrypt( $server[ 'serverpassword' ] );

    $productSettings = json_decode( htmlspecialchars_decode( $data[ 'settings' ] ) );
    if( $productSettings->PassTaxes->$data[ 'server_id' ] == 0 ) {
        $amount = $data[ 'subtotal' ];
    }
    else {
        $amount = $data[ 'total' ];
    }

    $path = dirname( dirname( dirname( __DIR__ ) ) ) . '/includes/';
    if( ! defined( 'ONAPP_WRAPPER_INIT' ) ) {
        define( 'ONAPP_WRAPPER_INIT', $path . 'wrapper/OnAppInit.php' );
        require_once ONAPP_WRAPPER_INIT;
    }

    $payment = new OnApp_Payment;
    $payment->auth( $server[ 'serverip' ], $server[ 'serverusername' ], $server[ 'serverpassword' ] );
    $payment->_user_id = $data[ 'onapp_user_id' ];
    $payment->_amount = $amount;
    $payment->_invoice_number = $invoice_id;
    $payment->save();

    $error = $payment->getErrorsAsString();
    if( empty( $error ) ) {
        logactivity( 'OnApp payment was sent. Service ID #' . $data[ 'service_id' ] . ', amount: ' . $amount );
    }
    else {
        logactivity( 'ERROR with OnApp payment for service ID #' . $data[ 'service_id' ] . ': ' . $error );
    }

    if( $data[ 'status' ] == 'Suspended' ) {
        // check for other unpaid invoices for this service
        $qry = 'SELECT
                    tblinvoices.`id`
                FROM
                    tblinvoices
                RIGHT JOIN tblinvoiceitems ON
                    tblinvoiceitems.`invoiceid` = tblinvoices.`id`
                    AND tblinvoiceitems.`relid` = :serviceID
                WHERE
                    tblinvoices.`status` = "Unpaid"
                GROUP BY tblinvoices.`id`';
        $qry = str_replace( ':serviceID', $data[ 'service_id' ], $qry );
        $result = full_query( $qry );

        if( mysql_num_rows( $result ) == 0 ) {
            if( ! function_exists( 'serverunsuspendaccount' ) ) {
                require_once $path . 'modulefunctions.php';
            }
            serverunsuspendaccount( $data[ 'service_id' ] );
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
