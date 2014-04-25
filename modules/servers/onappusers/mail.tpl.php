<?php

logactivity( 'OnApp User Module: process mail templates file, called from module.' );

// create account template
$where = array();
$where[ 'type' ] = 'product';
$where[ 'name' ] = $_LANG[ 'onappuserscreateaccount' ];
if( ! mysql_num_rows( select_query( 'tblemailtemplates', 'id', $where ) ) ) {
    $fields = array();
    $fields[ 'type' ] = 'product';
    $fields[ 'name' ] = $where[ 'name' ];
    $fields[ 'subject' ] = 'OnApp account has been created';
    $fields[ 'message' ] = '<p>Dear {$client_name}</p>
                    <p>Your OnApp account has been created:<br />
                    login: {$service_username}<br />
                    password: {$service_password}</p>
                    <p></p> To login, visit http://{$service_server_ip}';
    $fields[ 'plaintext' ] = 0;
    if( ! insert_query( 'tblemailtemplates', $fields ) ) {
        return sprintf( $_LANG[ 'onappuserserrtmpladd' ], $_LANG[ 'onappuserscreateaccount' ] );
    }
}

// suspend account template
$where = array();
$where[ 'type' ] = 'product';
$where[ 'name' ] = $_LANG[ 'onappuserssuspendaccount' ];
if( ! mysql_num_rows( select_query( 'tblemailtemplates', 'id', $where ) ) ) {
    $fields = array();
    $fields[ 'type' ] = 'product';
    $fields[ 'name' ] = $where[ 'name' ];
    $fields[ 'subject' ] = 'OnApp account has been created';
    $fields[ 'message' ] = '<p>Dear {$client_name}</p>
                    <p>Your OnApp account has been suspended.</p>';
    $fields[ 'plaintext' ] = 0;
    if( ! insert_query( 'tblemailtemplates', $fields ) ) {
        return sprintf( $_LANG[ 'onappuserserrtmpladd' ], $_LANG[ 'onappuserssuspendaccount' ] );
    }
}

// unsuspend account template
$where = array();
$where[ 'type' ] = 'product';
$where[ 'name' ] = $_LANG[ 'onappusersunsuspendaccount' ];
if( ! mysql_num_rows( select_query( 'tblemailtemplates', 'id', $where ) ) ) {
    $fields = array();
    $fields[ 'type' ] = 'product';
    $fields[ 'name' ] = $where[ 'name' ];
    $fields[ 'subject' ] = 'OnApp account has been created';
    $fields[ 'message' ] = '<p>Dear {$client_name}</p>
                    <p>Your OnApp account has been unsuspended.</p>';
    $fields[ 'plaintext' ] = 0;
    if( ! insert_query( 'tblemailtemplates', $fields ) ) {
        return sprintf( $_LANG[ 'onappuserserrtmpladd' ], $_LANG[ 'onappusersunsuspendaccount' ] );
    }
}

// terminate account template
$where = array();
$where[ 'type' ] = 'product';
$where[ 'name' ] = $_LANG[ 'onappusersterminateaccount' ];
if( ! mysql_num_rows( select_query( 'tblemailtemplates', 'id', $where ) ) ) {
    $fields = array();
    $fields[ 'type' ] = 'product';
    $fields[ 'name' ] = $where[ 'name' ];
    $fields[ 'subject' ] = 'OnApp account has been created';
    $fields[ 'message' ] = '<p>Dear {$client_name}</p>
                    <p>Your OnApp account has been terminated.</p>';
    $fields[ 'plaintext' ] = 0;
    if( ! insert_query( 'tblemailtemplates', $fields ) ) {
        return sprintf( $_LANG[ 'onappuserserrtmpladd' ], $_LANG[ 'onappusersterminateaccount' ] );
    }
}

unset( $where, $fields );
unlink( __FILE__ );