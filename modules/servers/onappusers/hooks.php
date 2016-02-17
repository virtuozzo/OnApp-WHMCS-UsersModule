<?php

if( ! defined( 'WHMCS' ) ) {
    exit( 'This file cannot be accessed directly' );
}

function hook_onappusers_InvoicePaid( $vars ) {
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
    $path = dirname( dirname( dirname( __DIR__ ) ) ) . '/includes/';

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

    if( ! defined( 'ONAPP_WRAPPER_INIT' ) ) {
        define( 'ONAPP_WRAPPER_INIT', $path . 'wrapper/OnAppInit.php' );
        require_once ONAPP_WRAPPER_INIT;
    }

    $qry = 'SELECT
                `secure`,
                `username`,
                `hostname`,
                `password`,
                `ipaddress`
            FROM
                tblservers
            WHERE
                `type` = "onappusers"
                AND `id` = :serverID';
    $qry = str_replace( ':serverID', $data[ 'server_id' ], $qry );
    $result = full_query( $qry );
    $server = mysql_fetch_assoc( $result );
    $server[ 'password' ] = decrypt( $server[ 'password' ] );
    if( $server[ 'secure' ] ) {
        $server[ 'address' ] = 'https://';
    }
    else {
        $server[ 'address' ] = 'http://';
    }
    if( empty( $server[ 'ipaddress' ] ) ) {
        $server[ 'address' ] .= $server[ 'hostname' ];
    }
    else {
        $server[ 'address' ] .= $server[ 'ipaddress' ];
    }
    unset( $server[ 'ipaddress' ], $server[ 'hostname' ], $server[ 'secure' ] );

    # get OnApp amount
    $qry    = 'SELECT
                    `amount`
                FROM
                    `tblonappusers_invoices`
                WHERE
                    `id` = ' . $invoice_id;
    $res    = mysql_query( $qry );
    $amount = mysql_result( $res, 0 );

    $payment = new OnApp_Payment;
    $payment->auth( $server[ 'address' ], $server[ 'username' ], $server[ 'password' ] );
    $payment->_user_id = $data[ 'onapp_user_id' ];
    $payment->_amount = $amount;
    $payment->_invoice_number = $invoice_id;
    $payment->save();

    $where = array( 'id' => $invoice_id );
    delete_query( 'tblonappusers_invoices', $where );

    $error = $payment->getErrorsAsString();
    if( empty( $error ) ) {
        logactivity( 'OnApp payment was sent. Service ID #' . $data[ 'service_id' ] . ', amount: ' . $amount );
    }
    else {
        logactivity( 'ERROR with OnApp payment for service ID #' . $data[ 'service_id' ] . ': ' . $error );
    }
}

function hook_onappusers_AutoSuspend() {
    global $CONFIG;

    if( $CONFIG[ 'AutoSuspension' ] != 'on' ) {
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
            WHERE
                tblinvoices.`status` = "Unpaid"
                AND tblinvoiceitems.`type` = "onappusers"
                AND tblhosting.`domainstatus` = "Active"
                AND NOW() > DATE_ADD( tblinvoices.`duedate`, INTERVAL :days DAY )
                AND tblhosting.`overideautosuspend` != 1
            GROUP BY
                tblhosting.`id`';
    $qry = str_replace( ':days', $CONFIG[ 'AutoSuspensionDays' ], $qry );
    $result = full_query( $qry );

    $path = dirname( dirname( dirname( __DIR__ ) ) ) . '/includes/';
    if( ! function_exists( 'serversuspendaccount' ) ) {
        require_once $path . 'modulefunctions.php';
    }
    while( $data = mysql_fetch_assoc( $result ) ) {
        serversuspendaccount( $data[ 'id' ] );
    }
}

function hook_onappusers_AutoTerminate() {
    global $CONFIG;

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
            WHERE
                tblinvoices.`status` = "Unpaid"
                AND tblinvoiceitems.`type` = "onappusers"
                AND tblhosting.`domainstatus` = "Suspended"
                AND NOW() > DATE_ADD( tblinvoices.`duedate`, INTERVAL :days DAY )
                AND tblhosting.`overideautosuspend` != 1
            GROUP BY
                tblhosting.`id`';
    $qry = str_replace( ':days', $CONFIG[ 'AutoTerminationDays' ], $qry );
    $result = full_query( $qry );

    $path = dirname( dirname( dirname( __DIR__ ) ) ) . '/includes/';
    if( ! function_exists( 'serverterminateaccount' ) ) {
        require_once $path . 'modulefunctions.php';
    }
    while( $data = mysql_fetch_assoc( $result ) ) {
        serverterminateaccount( $data[ 'id' ] );
    }
}

add_hook( 'InvoicePaid', 1, 'hook_onappusers_InvoicePaid' );
add_hook( 'DailyCronJob', 1, 'hook_onappusers_AutoSuspend' );
add_hook( 'DailyCronJob', 2, 'hook_onappusers_AutoTerminate' );