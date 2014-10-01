<?php

logactivity( 'OnApp User Module: process mail templates file, called from module.' );

// create account template
$where = array();
$where[ 'type' ] = 'product';
$where[ 'name' ] = $_LANG[ 'onappuserscreateaccount' ];
if( !mysql_num_rows( select_query( 'tblemailtemplates', 'id', $where ) ) ) {
    $fields = array();
    $fields[ 'type' ] = 'product';
    $fields[ 'name' ] = $fields[ 'subject' ] = $where[ 'name' ];
    $fields[ 'message' ] = '<p>Dear {$client_name},</p>
                    <p>Your OnApp account has been created:<br />
                    login: {$service_username}<br />
                    password: {$service_password}</p>
                    <p></p> To login, visit http://{$service_server_ip}';
    $fields[ 'plaintext' ] = 0;
    insert_query( 'tblemailtemplates', $fields );
}

// suspend account template
$where = array();
$where[ 'type' ] = 'product';
$where[ 'name' ] = $_LANG[ 'onappuserssuspendaccount' ];
if( !mysql_num_rows( select_query( 'tblemailtemplates', 'id', $where ) ) ) {
    $fields = array();
    $fields[ 'type' ] = 'product';
    $fields[ 'name' ] = $fields[ 'subject' ] = $where[ 'name' ];
    $fields[ 'message' ] = '<p>Dear {$client_name},</p>
                    <p>Your OnApp account has been suspended.</p>';
    $fields[ 'plaintext' ] = 0;
    insert_query( 'tblemailtemplates', $fields );
}

// unsuspend account template
$where = array();
$where[ 'type' ] = 'product';
$where[ 'name' ] = $_LANG[ 'onappusersunsuspendaccount' ];
if( !mysql_num_rows( select_query( 'tblemailtemplates', 'id', $where ) ) ) {
    $fields = array();
    $fields[ 'type' ] = 'product';
    $fields[ 'name' ] = $fields[ 'subject' ] = $where[ 'name' ];
    $fields[ 'message' ] = '<p>Dear {$client_name},</p>
                    <p>Your OnApp account has been unsuspended.</p>';
    $fields[ 'plaintext' ] = 0;
    insert_query( 'tblemailtemplates', $fields );
}

// terminate account template
$where = array();
$where[ 'type' ] = 'product';
$where[ 'name' ] = $_LANG[ 'onappusersterminateaccount' ];
if( !mysql_num_rows( select_query( 'tblemailtemplates', 'id', $where ) ) ) {
    $fields = array();
    $fields[ 'type' ] = 'product';
    $fields[ 'name' ] = $fields[ 'subject' ] = $where[ 'name' ];
    $fields[ 'message' ] = '<p>Dear {$client_name},</p>
                    <p>Your OnApp account has been terminated.</p>';
    $fields[ 'plaintext' ] = 0;
    insert_query( 'tblemailtemplates', $fields );
}

// upgrade account template
$where = array();
$where[ 'type' ] = 'product';
$where[ 'name' ] = $_LANG[ 'onappusersupgradeaccount' ];
if( !mysql_num_rows( select_query( 'tblemailtemplates', 'id', $where ) ) ) {
    $fields = array();
    $fields[ 'type' ] = 'product';
    $fields[ 'name' ] = $fields[ 'subject' ] = $where[ 'name' ];
    $fields[ 'message' ] = '<p>Dear {$client_name},</p>
                    <p>Your OnApp account has been upgraded.</p>';
    $fields[ 'plaintext' ] = 0;
    insert_query( 'tblemailtemplates', $fields );
}

// change account password
$where = array();
$where[ 'type' ] = 'product';
$where[ 'name' ] = $_LANG[ 'onappuserschangeaccountpassword' ];
if( !mysql_num_rows( select_query( 'tblemailtemplates', 'id', $where ) ) ) {
    $fields = array();
    $fields[ 'type' ] = 'product';
    $fields[ 'name' ] = $fields[ 'subject' ] = $where[ 'name' ];
    $fields[ 'message' ] = '<p>Dear {$client_name},</p>
                    <p>New password for your OnApp account has been generated:<br />
                    login: {$service_username}<br />
                    password: {$service_password}</p>
                    <p></p> To login, visit http://{$service_server_ip}';
    $fields[ 'plaintext' ] = 0;
    insert_query( 'tblemailtemplates', $fields );
}

unset( $where, $fields );
unlink( __FILE__ );