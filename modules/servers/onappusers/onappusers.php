<?php

if( ! defined( 'ONAPP_WRAPPER_INIT' ) ) {
    define( 'ONAPP_WRAPPER_INIT', ROOTDIR . '/includes/wrapper/OnAppInit.php' );
}

if( file_exists( ONAPP_WRAPPER_INIT ) ) {
    require_once ONAPP_WRAPPER_INIT;
}

loadLang();

if( file_exists( $file = __DIR__ . '/module.sql' ) ) {
    logactivity( 'OnApp User Module: process SQL file, called from module.' );

    $sql = file_get_contents( $file );
    $sql = explode( PHP_EOL . PHP_EOL, $sql );

    foreach( $sql as $qry ) {
        full_query( $qry );
    }
    unlink( $file );

    // Add e-mail templates
    require_once __DIR__ . '/mail.tpl.php';
}

function onappusers_ConfigOptions() {
    global $_LANG;

    if( ! file_exists( ONAPP_WRAPPER_INIT ) ) {
        $configArray = array(
            $_LANG[ 'onappwrappernotfound' ] . realpath( ROOTDIR ) . '/includes/wrapper' => array()
        );

        return $configArray;
    }

    $js = '<script type="text/javascript" src="../modules/servers/onappusers/includes/js/onappusers.js"></script>';
    $js .= '<script type="text/javascript" src="../modules/servers/onappusers/includes/js/tz.js"></script>';
    $js .= '<script type="text/javascript" src="../modules/servers/onappusers/includes/js/jquery.json-2.2.min.js"></script>';

    $serverGroup = isset( $_GET[ 'servergroup' ] ) ? $_GET[ 'servergroup' ] : (int)$GLOBALS[ 'servergroup' ];
    $sql = 'SELECT
                srv.`id`,
                srv.`name`,
                srv.`ipaddress` AS serverip,
                srv.`hostname` AS serverhostname,
                srv.`username` AS serverusername,
                srv.`password` AS serverpassword
            FROM
                `tblservers` AS srv
            LEFT JOIN
                `tblservergroupsrel` AS rel ON srv.`id` = rel.`serverid`
            LEFT JOIN
                `tblservergroups` AS grp ON grp.`id` = rel.`groupid`
            WHERE
                grp.`id` = :servergroup
                AND srv.`disabled` = 0';
    $sql = str_replace( ':servergroup', $serverGroup, $sql );

    $res = full_query( $sql );
    $serversData = array();
    if( mysql_num_rows( $res ) == 0 ) {
        $serversData[ 'NoServers' ] = sprintf( $_LANG[ 'onappuserserrorholder' ], $_LANG[ 'onappuserserrornoserveringroup' ] );
    }
    else {
        while( $onappConfig = mysql_fetch_assoc( $res ) ) {
            //Error if server adress (IP and hostname) not set
            if( empty( $onappConfig[ 'serverip' ] ) && empty( $onappConfig[ 'serverhostname' ] ) ) {
                $msg = sprintf( $_LANG[ 'onapperrcantfoundadress' ] );

                $data[ 'Name' ] = $onappConfig[ 'name' ];
                $data[ 'NoAddress' ] = sprintf( $_LANG[ 'onappuserserrorholder' ], $msg );
                $serversData[ $onappConfig[ 'id' ] ] = $data;
                continue;
            }
            $onappConfig[ 'serverpassword' ] = decrypt( $onappConfig[ 'serverpassword' ] );

            $data = array();
            $module = new OnApp_UserModule( $onappConfig );

            // handle billing plans/groups per server
            $data[ 'BillingPlans' ] = $module->getBillingPlans();
            if( empty( $data[ 'BillingPlans' ] ) ) {
                $msg = sprintf( $_LANG[ 'onappusersnobillingplans' ] );
                $data[ 'BillingPlans' ] = sprintf( $_LANG[ 'onappuserserrorholder' ], $msg );
            }

            //handle user roles per server
            $data[ 'Roles' ] = $module->getRoles();
            if( empty( $data[ 'Roles' ] ) ) {
                $msg = sprintf( $_LANG[ 'onappusersnoroles' ] );
                $data[ 'Roles' ] = sprintf( $_LANG[ 'onappuserserrorholder' ], $msg );
            }

            // handle user groups per server
            $data[ 'UserGroups' ] = $module->getUserGroups();
            if( empty( $data[ 'UserGroups' ] ) ) {
                $msg = sprintf( $_LANG[ 'onappusersnousergroups' ] );
                $data[ 'UserGroups' ] = sprintf( $_LANG[ 'onappuserserrorholder' ], $msg );
            }

            //handle locales per server
            $data[ 'Locales' ] = $module->getLocales();

            $data[ 'Name' ] = $onappConfig[ 'name' ];
            $serversData[ $onappConfig[ 'id' ] ] = $data;
            unset( $data );
        }

        $sql = 'SELECT
                    prod.`configoption1` AS options,
                    prod.`servergroup` AS `group`
                FROM
                    `tblproducts` AS prod
                WHERE
                    prod.`id` = :id';
        $sql = str_replace( ':id', (int)$_GET[ 'id' ], $sql );
        $results = full_query( $sql );
        $results = mysql_fetch_assoc( $results );
        $results[ 'options' ] = htmlspecialchars_decode( $results[ 'options' ] );
        $serversData[ 'Group' ] = $results[ 'group' ];
        if( ! empty( $results[ 'options' ] ) ) {
            $results[ 'options' ] = json_decode( $results[ 'options' ], true );
            $serversData += $results[ 'options' ];
        }

        $js .= '<script type="text/javascript">'
            . 'var ServersData = ' . json_encode( $serversData ) . ';'
            . 'var ONAPP_LANG = ' . getJSLang() . ';'
            . '</script>';

        if( isset( $_GET[ 'servergroup' ] ) ) {
            ob_end_clean();
            exit( json_encode( $serversData ) );
        }

        $configArray = array(
            sprintf( '' ) => array(
                'Description' => $js
            ),
        );
    }

    return $configArray;
}

function onappusers_CreateAccount( $params ) {
    global $CONFIG, $_LANG;

    if( ! file_exists( ONAPP_WRAPPER_INIT ) ) {
        return $_LANG[ 'onappwrappernotfound' ] . realpath( ROOTDIR ) . '/includes';
    }

    // Unique ID of the product/service in the WHMCS Database
    $serviceid = $params[ 'serviceid' ];
    $username = $params[ 'username' ];
    $password = $params[ 'password' ];
    // Array of clients details - firstname, lastname, email, country, etc...
    $clientsdetails = $params[ 'clientsdetails' ];

    // Save hosting password
    if( substr( $params[ 'password' ], - 1 ) !== '#' ) {
        $randomString = substr( str_shuffle( '~!@$%^&*(){}|0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ' ), 0, 15 );
        $password = $randomString . '#';

        full_query(
            "UPDATE
                tblhosting
            SET
                password = '" . encrypt( $password ) . "'
                WHERE
                    id = '$serviceid'"
        );
    }

    // Save hosting username
    if( ! $username ) {
        $username = $clientsdetails[ 'email' ];
        full_query(
            "UPDATE
                    tblhosting
                SET
                    username = '$username'
                WHERE
                    id = '$serviceid'"
        );
    }

    if( ! $password ) {
        return $_LANG[ 'onappuserserrusercreate' ] . '<br/>' . PHP_EOL . $_LANG[ 'onappuserserrpwdnotset' ];
    }

    $module = new OnApp_UserModule( $params );
    $onapp_user = $module->getOnAppObject( 'OnApp_User' );
    $onapp_user->_email = $clientsdetails[ 'email' ];
    $onapp_user->_password = $onapp_user->_password_confirmation = $password;
    $onapp_user->_password = $password;
    $onapp_user->_login = $username;
    $onapp_user->_first_name = $clientsdetails[ 'firstname' ];
    $onapp_user->_last_name = $clientsdetails[ 'lastname' ];

    $params[ 'configoption1' ] = html_entity_decode( $params[ 'configoption1' ] );
    $params[ 'configoption1' ] = json_decode( $params[ 'configoption1' ], true );
    // Assign billing group/plan to user
    $group_id = $params[ 'configoption1' ][ 'SelectedPlans' ][ $params[ 'serverid' ] ];

    $onapp_user->_billing_plan_id = $group_id;

    $tmp = array();
    $onapp_user->_role_ids = array_merge( $tmp, $params[ 'configoption1' ][ 'SelectedRoles' ][ $params[ 'serverid' ] ] );

    // Assign TZ to user
    $onapp_user->_time_zone = $params[ 'configoption1' ][ 'SelectedTZs' ][ $params[ 'serverid' ] ];

    // Assign user group to user
    $onapp_user->_user_group_id = $params[ 'configoption1' ][ 'SelectedUserGroups' ][ $params[ 'serverid' ] ];

    // Assign locale to user
    if( isset( $params[ 'configoption1' ][ 'SelectedLocales' ][ $params[ 'serverid' ] ] ) ) {
        $onapp_user->_locale = $params[ 'configoption1' ][ 'SelectedLocales' ][ $params[ 'serverid' ] ];
    }

    $onapp_user->save();
    if( ! is_null( $onapp_user->getErrorsAsArray() ) ) {
        $error_msg = $_LANG[ 'onappuserserrusercreate' ] . ': ';
        $error_msg .= $onapp_user->getErrorsAsString( ', ' );

        return $error_msg;
    }

    if( ! is_null( $onapp_user->_obj->getErrorsAsArray() ) ) {
        $error_msg = $_LANG[ 'onappuserserrusercreate' ] . ': ';
        $error_msg .= $onapp_user->_obj->getErrorsAsString( ', ' );

        return $error_msg;
    }

    if( is_null( $onapp_user->_obj->_id ) ) {
        return $_LANG[ 'onappuserserrusercreate' ];
    }

    // Save user data in whmcs db
    insert_query( 'tblonappusers', array(
        'server_id'     => $params[ 'serverid' ],
        'client_id'     => $clientsdetails[ 'userid' ],
        'service_id'    => $serviceid,
        'onapp_user_id' => $onapp_user->_obj->_id,
    ) );

    sendmessage( $_LANG[ 'onappuserscreateaccount' ], $serviceid );

    return 'success';
}

function onappusers_TerminateAccount( $params ) {
    global $_LANG;

    if( ! file_exists( ONAPP_WRAPPER_INIT ) ) {
        return $_LANG[ 'onappwrappernotfound' ] . realpath( ROOTDIR ) . '/includes';
    }

    $serviceid = $params[ 'serviceid' ];
    $client_id = $params[ 'clientsdetails' ][ 'userid' ];

    $server_id = $params[ 'serverid' ];

    $query = "SELECT
                    onapp_user_id
                FROM
                    tblonappusers
                WHERE
                    server_id = $server_id
                    AND client_id = $client_id
                    AND service_id = $serviceid";

    $result = full_query( $query );
    if( $result ) {
        $onapp_user_id = mysql_result( $result, 0 );
    }
    if( ! $onapp_user_id ) {
        return sprintf( $_LANG[ 'onappuserserrassociateuser' ], $client_id, $server_id );
    }

    $module = new OnApp_UserModule( $params );
    $onapp_user = $module->getOnAppObject( 'OnApp_User' );
    $vms = $module->getOnAppObject( 'OnApp_VirtualMachine' );
    if( $vms->getList( $onapp_user_id ) ) {
        $error_msg = $_LANG[ 'onappuserserruserterminate' ];

        return $error_msg;
    }

    $onapp_user->_id = $onapp_user_id;
    $onapp_user->delete( true );

    if( ! empty( $onapp_user->error ) ) {
        $error_msg = $_LANG[ 'onappuserserruserdelete' ] . ': ';
        $error_msg .= $onapp_user->getErrorsAsString( ', ' );

        return $error_msg;
    }
    else {
        $query = 'DELETE FROM
                        tblonappusers
                    WHERE
                        service_id = ' . (int)$serviceid . '
                        AND client_id = ' . (int)$client_id . '
                        AND server_id = ' . (int)$server_id;
        full_query( $query );
    }

    sendmessage( $_LANG[ 'onappusersterminateaccount' ], $serviceid );

    return 'success';
}

function onappusers_SuspendAccount( $params ) {
    global $_LANG;

    if( ! file_exists( ONAPP_WRAPPER_INIT ) ) {
        return $_LANG[ 'onappwrappernotfound' ] . realpath( ROOTDIR ) . '/includes';
    }

    $serviceid = $params[ 'serviceid' ];
    $client_id = $params[ 'clientsdetails' ][ 'userid' ];
    $server_id = $params[ 'serverid' ];

    $query = "SELECT
                    onapp_user_id
                FROM
                    tblonappusers
                WHERE
                    server_id = $server_id
                    AND client_id = $client_id
                    AND service_id = $serviceid";

    $result = full_query( $query );
    if( $result ) {
        $onapp_user_id = mysql_result( $result, 0 );
    }
    if( ! $onapp_user_id ) {
        return sprintf( $_LANG[ 'onappuserserrassociateuser' ], $client_id, $server_id );
    }

    $module = new OnApp_UserModule( $params );
    $onapp_user = $module->getOnAppObject( 'OnApp_User' );

    $onapp_user->_id = $onapp_user_id;
    $onapp_user->suspend();

    if( ! is_null( $onapp_user->error ) ) {
        $error_msg = $_LANG[ 'onappuserserrusersuspend' ] . ':<br/>';
        $error_msg .= $onapp_user->getErrorsAsString( '<br/>' );

        return $error_msg;
    }

    sendmessage( $_LANG[ 'onappuserssuspendaccount' ], $serviceid );

    return 'success';
}

function onappusers_UnsuspendAccount( $params ) {
    global $_LANG;

    if( ! file_exists( ONAPP_WRAPPER_INIT ) ) {
        return $_LANG[ 'onappwrappernotfound' ] . realpath( ROOTDIR ) . '/includes';
    }

    $serviceid = $params[ 'serviceid' ];
    $client_id = $params[ 'clientsdetails' ][ 'userid' ];
    $server_id = $params[ 'serverid' ];

    $query = "SELECT
                    onapp_user_id
                FROM
                    tblonappusers
                WHERE
                    server_id = '$server_id'
                    AND client_id = '$client_id'
                    AND service_id = '$serviceid'";

    $result = full_query( $query );
    if( $result ) {
        $onapp_user_id = mysql_result( $result, 0 );
    }
    if( ! $onapp_user_id ) {
        return sprintf( $_LANG[ 'onappuserserrassociateuser' ], $client_id, $server_id );
    }

    $module = new OnApp_UserModule( $params );
    $onapp_user = $module->getOnAppObject( 'OnApp_User' );
    $onapp_user->_id = $onapp_user_id;
    $onapp_user->activate_user();

    if( ! is_null( $onapp_user->error ) ) {
        $error_msg = $_LANG[ 'onappuserserruserunsuspend' ] . ':<br/>';
        $error_msg .= $onapp_user->getErrorsAsString( '<br/>' );

        return $error_msg;
    }

    sendmessage( $_LANG[ 'onappusersunsuspendaccount' ], $serviceid );

    return 'success';
}

function onappusers_ChangePackage( $params ) {
    global $_LANG;

    if( $params[ 'action' ] !== 'upgrade' ) {
        return;
    }

    $config = json_decode( $params[ 'configoption1' ] );
    $serviceid = $params[ 'serviceid' ];
    $client_id = $params[ 'clientsdetails' ][ 'userid' ];
    $server_id = $params[ 'serverid' ];

    $query = "SELECT
                    onapp_user_id
                FROM
                    tblonappusers
                WHERE
                    server_id = '$server_id'
                    AND client_id = '$client_id'
                    AND service_id = '$serviceid'";

    $result = full_query( $query );
    $OnAppUserID = mysql_result( $result, 0 );

    $module = new OnApp_UserModule( $params );
    $OnAppUser = $module->getOnAppObject( 'OnApp_User' );
    $OnAppUser->_id = $OnAppUserID;
    $OnAppUser->_time_zone = $config->SelectedTZs->$params[ 'serverid' ];
    $OnAppUser->_locale = $config->SelectedLocales->$params[ 'serverid' ];
    $OnAppUser->_billing_plan_id = $config->SelectedPlans->$params[ 'serverid' ];
    $OnAppUser->_user_group_id = $config->SelectedUserGroups->$params[ 'serverid' ];
    $OnAppUser->save();

    if( ! is_null( $OnAppUser->error ) ) {
        $errorMsg = $_LANG[ 'onappuserserruserupgrade' ] . ':<br/>';
        $errorMsg .= $OnAppUser->getErrorsAsString( '<br/>' );

        return $errorMsg;
    }

    sendmessage( $_LANG[ 'onappusersupgradeaccount' ], $serviceid );

    return 'success';
}

function loadLang() {
    global $_LANG, $CONFIG;

    $currentDir = getcwd();
    chdir( dirname( __FILE__ ) . '/lang/' );
    $availableLangs = glob( '*.txt' );

    $language = isset( $_SESSION[ 'Language' ] ) ? $_SESSION[ 'Language' ] : $CONFIG[ 'Language' ];
    $language = ucfirst( $language ) . '.txt';

    if( ! in_array( $language, $availableLangs ) ) {
        $language = 'English.txt';
    }

    $templang = file_get_contents( dirname( __FILE__ ) . '/lang/' . $language );
    eval ( $templang );
    chdir( $currentDir );
}

function getJSLang() {
    global $_LANG;

    return json_encode( $_LANG );
}

function parseLang( &$html ) {
    $html = preg_replace_callback(
        '#{\$LANG.(.*)}#',
        create_function(
            '$matches',
            'global $_LANG; return $_LANG[ $matches[ 1 ] ];'
        ),
        $html
    );

    return $html;
}

function onappusers_ClientArea( $params = '' ) {
    if( isset( $_GET[ 'getstat' ] ) ) {
        onappusers_OutstandingDetails( $params );
    }

    $sets = json_decode( htmlspecialchars_decode( $params[ 'configoption1' ] ), true );

    $html = '';
    if( $sets[ 'ShowControlPanel' ][ $params[ 'serverid' ] ] ) {
        $html .= injectServerRow( $params );
    }

    if( $sets[ 'ShowStat' ][ $params[ 'serverid' ] ] ) {
        $html .= file_get_contents( dirname( __FILE__ ) . '/includes/html/clientarea.html' );
    }

    parseLang( $html );
    $html .= '<script type="text/javascript">'
        . 'var UID = ' . $params[ 'clientsdetails' ][ 'userid' ] . ';'
        . 'var PID = ' . $params[ 'accountid' ] . ';'
        . 'var LANG = ' . getJSLang() . ';</script>';

    return $html;
}

function injectServerRow( $params ) {
    $iv_size = mcrypt_get_iv_size( MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB );
    $iv = mcrypt_create_iv( $iv_size, MCRYPT_RAND );
    $key = substr( md5( uniqid( rand( 1, 999999 ), true ) ), 0, 27 );

    $server = ! empty( $params[ 'serverip' ] ) ? $params[ 'serverip' ] : $params[ 'serverhostname' ];
    if( strpos( $server, 'http' ) === false ) {
        $scheme = $params[ 'serversecure' ] ? 'https://' : 'http://';
        $server = $scheme . $server;
    }

    $data = array(
        'login'    => $params[ 'username' ],
        'password' => $params[ 'password' ],
        'server'   => $server,
    );
    $data = json_encode( $data ) . '%%%';

    $crypttext = mcrypt_encrypt( MCRYPT_RIJNDAEL_256, $key, $data, MCRYPT_MODE_ECB, $iv );
    $_SESSION[ 'utk' ] = array(
        $key . substr( md5( uniqid( rand( 1, 999999 ), true ) ), rand( 0, 26 ), 5 ),
        base64_encode( base64_encode( $crypttext ) )
    );

    $html = file_get_contents( dirname( __FILE__ ) . '/includes/html/serverData.html' );
    $html = str_replace( '{###}', md5( uniqid( rand( 1, 999999 ), true ) ), $html );
    $html .= '<script type="text/javascript">'
        . 'var SERVER = "' . $server . '";'
        . 'var injTarget = "' . $params[ 'username' ] . ' / ' . $params[ 'password' ] . '";'
        . '</script>';

    return $html;
}

function onappusers_OutstandingDetails( $params = '' ) {
    $limit = 10;
    if( $_GET[ 'tz_offset' ] != 0 ) {
        $date_start = date( 'Y-m-d H:00:00', strtotime( $_GET[ 'start' ] ) + ( $_GET[ 'tz_offset' ] * 60 ) );
        $date_end = date( 'Y-m-d H:00:00', strtotime( $_GET[ 'end' ] ) + ( $_GET[ 'tz_offset' ] * 60 ) );
    }
    else {
        $date_start = mysql_real_escape_string( $_GET[ 'start' ] );
        $date_end = mysql_real_escape_string( $_GET[ 'end' ] );
    }
    $date_end = substr_replace( $date_end, '10', - 5, 2 );

    $sql = 'SELECT
                `server_id`,
                `client_id` AS whmcs_user_id,
                `onapp_user_id`
            FROM
                `tblonappusers`
            WHERE
                `service_id` = ' . $params[ 'serviceid' ] . '
            LIMIT 1';
    $user = mysql_fetch_assoc( full_query( $sql ) );

    $page = (int)$_GET[ 'page' ];
    $start = ( $page - 1 ) * $limit;

    $sql = 'SELECT
                SQL_CALC_FOUND_ROWS
                `stat`.`date` AS ORIGINAL_DATE,
                ADDTIME( `stat`.`date`, SEC_TO_TIME( -( ' . $_GET[ 'tz_offset' ] . ' ) * 60 ) ) AS `date`,
                `stat`.`currency`,
                vm.*
            FROM
                `onapp_itemized_stat` AS stat
            JOIN `onapp_itemized_virtual_machines` AS vm
                ON vm.`stat_id` = stat.`id`
            WHERE
                `whmcs_user_id` = ' . $user[ 'whmcs_user_id' ] . '
                AND `onapp_user_id` = ' . $user[ 'onapp_user_id' ] . '
                AND `server_id` = ' . $params[ 'serverid' ] . '
                AND `date` BETWEEN "' . $date_start . '"
                AND ADDTIME( "' . $date_end . '", "00:10" )
            ORDER BY `date` DESC
            LIMIT ' . $start . ', ' . $limit;
    $res = full_query( $sql );

    $total = mysql_result( mysql_query( 'SELECT FOUND_ROWS()' ), 0 );

    $stat = array();
    while( $row = mysql_fetch_assoc( $res ) ) {
        $tmp = array();

        //get disks
        $sql = 'SELECT * FROM `onapp_itemized_disks` WHERE `stat_id` = ' . $row[ 'stat_id' ];
        $res_tmp = full_query( $sql );
        while( $item = mysql_fetch_assoc( $res_tmp ) ) {
            $tmp[ 'stat' ][ 'disks' ][ ] = $item;
        }

        //get networks
        $sql = 'SELECT * FROM `onapp_itemized_network_interfaces` WHERE `stat_id` = ' . $row[ 'stat_id' ];
        $res_tmp = full_query( $sql );
        while( $item = mysql_fetch_assoc( $res_tmp ) ) {
            $tmp[ 'stat' ][ 'nets' ][ ] = $item;
        }

        $stat[ ] = array_merge( $row, $tmp );
    }

    //get used resources stat
    $sql = 'SELECT
                SUM( `user_resources_cost` ) AS resources,
                SUM( `backup_cost` ) AS backup,
                SUM( `monit_cost` ) AS monitis,
                SUM( `storage_disk_size_cost` ) AS storage,
                SUM( `template_cost` ) AS template,
                SUM( `edge_group_cost` ) AS edgecdn,
                SUM( `vm_cost` ) AS vm,
                SUM( `total_cost` ) AS total
            FROM
                `onapp_itemized_resources`
            WHERE
                `whmcs_user_id` = ' . $user[ 'whmcs_user_id' ] . '
                AND `onapp_user_id` = ' . $user[ 'onapp_user_id' ] . '
                AND `server_id` = ' . $params[ 'serverid' ] . '
                AND `date` BETWEEN "' . $date_start . '"
                AND "' . $date_end . '"';
    $resources = mysql_fetch_assoc( full_query( $sql ) );

    $data = array(
        'total'     => $total,
        'page'      => $page,
        'resources' => $resources,
        'stat'      => $stat,
        'limit'     => $limit,
    );
    $data = json_encode( $data );
    exit( $data );
}

class OnApp_UserModule {
    private $server;

    public function __construct( $params ) {
        $this->server = new stdClass;
        $this->server->ip = empty( $params[ 'serverip' ] ) ? $params[ 'serverhostname' ] : $params[ 'serverip' ];
        $this->server->user = $params[ 'serverusername' ];
        $this->server->pass = $params[ 'serverpassword' ];
    }

    public function getUserGroups() {
        $data = $this->getOnAppObject( 'OnApp_UserGroup' )->getList();

        return $this->buildArray( $data );
    }

    public function getRoles() {
        $data = $this->getOnAppObject( 'OnApp_Role' )->getList();

        return $this->buildArray( $data );
    }

    public function getBillingPlans() {
        $data = $this->getOnAppObject( 'OnApp_BillingPlan' )->getList();

        return $this->buildArray( $data );
    }

    public function getLocales() {
        $tmp = array();
        foreach( $this->getOnAppObject( 'OnApp_Locale' )->getList() as $locale ) {
            if( empty( $locale->name ) ) {
                continue;
            }
            $tmp[ $locale->code ] = $locale->name;
        }

        return $tmp;
    }

    public function getOnAppObject( $class ) {
        $obj = new $class;
        $obj->auth( $this->server->ip, $this->server->user, $this->server->pass );

        return $obj;
    }

    private function buildArray( $data ) {
        $tmp = array();
        foreach( $data as $item ) {
            $tmp[ $item->_id ] = $item->_label;
        }

        return $tmp;
    }
}