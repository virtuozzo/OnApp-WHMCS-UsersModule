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
    require_once __DIR__ . '/module.mail.php';
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

    $serverGroup = isset( $_GET[ 'servergroup' ] ) ? $_GET[ 'servergroup' ] : (int)$GLOBALS[ 'servergroup' ];
    $sql = 'SELECT
                srv.`id`,
                srv.`name`,
                srv.`ipaddress` AS serverip,
                srv.`secure` AS serversecure,
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
    $error = false;
    if( mysql_num_rows( $res ) == 0 ) {
        $serversData[ 'NoServers' ] = sprintf( $_LANG[ 'onappuserserrorholder' ], $_LANG[ 'onappuserserrornoserveringroup' ] );
        $error = true;
    } else {
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

            //compare wrapper version with API

            $compareResult = $module->checkWrapperVersion();

            if( !$compareResult['status'] ){
                $error = $_LANG[ 'onappneedupdatewrapper' ] . ' (wrapper version: ' . $compareResult['wrapperVersion'] . '; ' . 'api version: ' . $compareResult['apiVersion'] . ')';
                if($compareResult['apiMessage'] != ''){
                    $error .= '; ' . $compareResult['apiMessage'];
                }
                $configArray = array(
                    $error => array()
                );
                return $configArray;
            }

            // handle billing plans per server
            $data[ 'BillingPlans' ] = $data[ 'SuspendedBillingPlans' ] = $module->getBillingPlans();
            if( empty( $data[ 'BillingPlans' ] ) ) {
                $msg = sprintf( $_LANG[ 'onappusersnobillingplans' ] );
                $data[ 'BillingPlans' ] = $data[ 'SuspendedBillingPlans' ] = sprintf( $_LANG[ 'onappuserserrorholder' ], $msg );
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
        if(!isset($_GET[ 'id' ])){
            preg_match_all( '/id=(\d+)/', $_SERVER['HTTP_REFERER'], $matches);
            $sql = str_replace( ':id', (int)$matches[1][0], $sql );
        }else{
            $sql = str_replace( ':id', (int)$_GET[ 'id' ], $sql );
        }

        $results = full_query( $sql );
        $results = mysql_fetch_assoc( $results );

        $results[ 'options' ] = htmlspecialchars_decode( $results[ 'options' ] );
        $serversData[ 'Group' ] = $results[ 'group' ];

        if( ! empty( $results[ 'options' ] ) ) {
            $results[ 'options' ] = json_decode( $results[ 'options' ], true );
            $serversData += $results[ 'options' ];
        }

    }

    $js .= '<script type="text/javascript">'
           . 'var ServersData = ' . json_encode( $serversData ) . ';'
           . 'var ONAPP_LANG = ' . getJSLang() . ';'
           . 'buildFields( ServersData );'
           . '</script>';

    if( (!$error) && isset( $_GET[ 'servergroup' ] ) ) {
        ob_end_clean();
        exit( json_encode( $serversData ) );
    }

    $configArray = array(
        sprintf( '' ) => array(
            'Description' => $js
        ),
    );

    return $configArray;
}

function onappusers_CreateAccount( $params ) {
    global $CONFIG, $_LANG;

    if( ! file_exists( ONAPP_WRAPPER_INIT ) ) {
        return $_LANG[ 'onappwrappernotfound' ] . realpath( ROOTDIR ) . '/includes';
    }

    $clientsDetails = $params[ 'clientsdetails' ];
    $serviceID = $params[ 'serviceid' ];
    $userName = $params[ 'username' ] ? $params[ 'username' ] : $clientsDetails[ 'email' ];
    $password = OnApp_UserModule::generatePassword();

    if( ! $password ) {
        return $_LANG[ 'onappuserserrusercreate' ] . ': ' . $_LANG[ 'onappuserserrpwdnotset' ];
    }

    $module = new OnApp_UserModule( $params );
    $OnAppUser = $module->getOnAppObject( 'OnApp_User' );
    $OnAppUser->_email = $clientsDetails[ 'email' ];
    $OnAppUser->_password = $OnAppUser->_password_confirmation = $password;
    $OnAppUser->_login = $userName;
    $OnAppUser->_first_name = $clientsDetails[ 'firstname' ];
    $OnAppUser->_last_name = $clientsDetails[ 'lastname' ];

    $params[ 'configoption1' ] = html_entity_decode( $params[ 'configoption1' ] );
    $params[ 'configoption1' ] = json_decode( $params[ 'configoption1' ], true );
    // Assign billing group/plan to user
    $billingPlanID = $params[ 'configoption1' ][ 'SelectedPlans' ][ $params[ 'serverid' ] ];
    $OnAppUser->_billing_plan_id = $billingPlanID;

    $tmp = array();
    $OnAppUser->_role_ids = array_merge( $tmp, $params[ 'configoption1' ][ 'SelectedRoles' ][ $params[ 'serverid' ] ] );

    // Assign TZ to user
    $OnAppUser->_time_zone = $params[ 'configoption1' ][ 'SelectedTZs' ][ $params[ 'serverid' ] ];

    // Assign user group to user
    $OnAppUser->_user_group_id = $params[ 'configoption1' ][ 'SelectedUserGroups' ][ $params[ 'serverid' ] ];

    // Assign locale to user
    if( isset( $params[ 'configoption1' ][ 'SelectedLocales' ][ $params[ 'serverid' ] ] ) ) {
        $OnAppUser->_locale = $params[ 'configoption1' ][ 'SelectedLocales' ][ $params[ 'serverid' ] ];
    }

    $OnAppUser->save();
    if( ! is_null( $OnAppUser->getErrorsAsArray() ) ) {
        $errorMsg = $_LANG[ 'onappuserserrusercreate' ] . ': ';
        $errorMsg .= $OnAppUser->getErrorsAsString( ', ' );

        return $errorMsg;
    }

    if( ! is_null( $OnAppUser->_obj->getErrorsAsArray() ) ) {
        $errorMsg = $_LANG[ 'onappuserserrusercreate' ] . ': ';
        $errorMsg .= $OnAppUser->_obj->getErrorsAsString( ', ' );

        return $errorMsg;
    }

    if( is_null( $OnAppUser->_obj->_id ) ) {
        return $_LANG[ 'onappuserserrusercreate' ];
    }

    // Save user link in whmcs db
    insert_query( 'tblonappusers', array(
        'server_id'     => $params[ 'serverid' ],
        'client_id'     => $clientsDetails[ 'userid' ],
        'service_id'    => $serviceID,
        'onapp_user_id' => $OnAppUser->_obj->_id,
    ) );

    // Save OnApp login and password
    full_query(
        "UPDATE
                tblhosting
            SET
                password = '" . encrypt( $password ) . "',
                username = '$userName'
            WHERE
                id = '$serviceID'"
    );

    sendmessage( $_LANG[ 'onappuserscreateaccount' ], $serviceID );

    return 'success';
}

function onappusers_TerminateAccount( $params ) {
    global $_LANG;

    if( ! file_exists( ONAPP_WRAPPER_INIT ) ) {
        return $_LANG[ 'onappwrappernotfound' ] . realpath( ROOTDIR ) . '/includes';
    }

    $serviceID = $params[ 'serviceid' ];
    $clientID = $params[ 'clientsdetails' ][ 'userid' ];
    $serverID = $params[ 'serverid' ];

    $query = "SELECT
                    onapp_user_id
                FROM
                    tblonappusers
                WHERE
                    service_id = $serviceID";

    $result = full_query( $query );
    if( $result ) {
        //$OnAppUserID = mysql_result( $result, 0 );
        $OnAppUserIDArr = mysql_fetch_assoc( $result );
        $OnAppUserID = $OnAppUserIDArr['onapp_user_id'];
    }
    if( ! $OnAppUserID ) {
        return sprintf( $_LANG[ 'onappuserserrassociateuserbyserviceid' ], $serviceID);
    }

    $module = new OnApp_UserModule( $params );
    $OnAppUser = $module->getOnAppObject( 'OnApp_User' );
    $OnAppUser->_id = $OnAppUserID;
    $OnAppUser->delete( true );

    if( ! empty( $OnAppUser->error ) ) {
        $errorMsg = $_LANG[ 'onappuserserruserdelete' ] . ': ';
        $errorMsg .= $OnAppUser->getErrorsAsString( ', ' );

        return $errorMsg;
    }
    else {
        $query = 'DELETE FROM
                        tblonappusers
                    WHERE
                        service_id = ' . (int)$serviceID;
        full_query( $query );
    }

    sendmessage( $_LANG[ 'onappusersterminateaccount' ], $serviceID );

    return 'success';
}

function onappusers_SuspendAccount( $params ) {
    global $_LANG;

    if( ! file_exists( ONAPP_WRAPPER_INIT ) ) {
        return $_LANG[ 'onappwrappernotfound' ] . realpath( ROOTDIR ) . '/includes';
    }

    $serverID = $params[ 'serverid' ];
    $clientID = $params[ 'clientsdetails' ][ 'userid' ];
    $serviceID = $params[ 'serviceid' ];
    $billingPlan = json_decode( $params[ 'configoption1' ] );
    $billingPlan = $billingPlan->SelectedSuspendedPlans->$serverID;

    $query = "SELECT
                    onapp_user_id
                FROM
                    tblonappusers
                WHERE
                    service_id = $serviceID";

    $result = full_query( $query );
    if( $result ) {
        //$OnAppUserID = mysql_result( $result, 0 );
        $OnAppUserIDArr = mysql_fetch_assoc( $result );
        $OnAppUserID = $OnAppUserIDArr['onapp_user_id'];
    }
    if( ! $OnAppUserID ) {
        return sprintf( $_LANG[ 'onappuserserrassociateuserbyserviceid' ], $serviceID);
    }

    $module = new OnApp_UserModule( $params );
    $OnAppUser = $module->getOnAppObject( 'OnApp_User' );

    $OnAppUser->_id = $OnAppUserID;
    if( isset( $billingPlan ) ) {
        $unset = array( 'time_zone', 'user_group_id', 'locale' );
        $OnAppUser->unsetFields( $unset );
        $OnAppUser->_billing_plan_id = $billingPlan;
        $OnAppUser->save();
    }
    $OnAppUser->suspend();

    if( ! is_null( $OnAppUser->error ) ) {
        $errorMsg = $_LANG[ 'onappuserserrusersuspend' ] . ':<br/>';
        $errorMsg .= $OnAppUser->getErrorsAsString( '<br/>' );

        return $errorMsg;
    }

    sendmessage( $_LANG[ 'onappuserssuspendaccount' ], $serviceID );

    return 'success';
}

function onappusers_UnsuspendAccount( $params ) {
    global $_LANG;

    if( ! file_exists( ONAPP_WRAPPER_INIT ) ) {
        return $_LANG[ 'onappwrappernotfound' ] . realpath( ROOTDIR ) . '/includes';
    }

    $serverID = $params[ 'serverid' ];
    $serviceID = $params[ 'serviceid' ];
    $billingPlan = json_decode( $params[ 'configoption1' ] );
    $billingPlan = $billingPlan->SelectedPlans->$serverID;

    $query = "SELECT
                    onapp_user_id
                FROM
                    tblonappusers
                WHERE
                    service_id = $serviceID";

    $result = full_query( $query );
    if( $result ) {
        //$OnAppUserID = mysql_result( $result, 0 );
        $OnAppUserIDArr = mysql_fetch_assoc( $result );
        $OnAppUserID = $OnAppUserIDArr['onapp_user_id'];
    }
    if( ! $OnAppUserID ) {
        return sprintf( $_LANG[ 'onappuserserrassociateuserbyserviceid' ], $serviceID);
    }

    $module = new OnApp_UserModule( $params );
    $OnAppUser = $module->getOnAppObject( 'OnApp_User' );
    $unset = array( 'time_zone', 'user_group_id', 'locale' );
    $OnAppUser->unsetFields( $unset );

    $OnAppUser->_id = $OnAppUserID;
    $OnAppUser->_billing_plan_id = $billingPlan;
    $OnAppUser->save();
    $OnAppUser->activate_user();

    if( ! is_null( $OnAppUser->error ) ) {
        $errorMsg = $_LANG[ 'onappuserserruserunsuspend' ] . ':<br/>';
        $errorMsg .= $OnAppUser->getErrorsAsString( '<br/>' );

        return $errorMsg;
    }

    sendmessage( $_LANG[ 'onappusersunsuspendaccount' ], $serviceID );

    return 'success';
}

function onappusers_ChangePackage( $params ) {
    global $_LANG;

    if( $params[ 'action' ] !== 'upgrade' ) {
        return;
    }

    $config = json_decode( $params[ 'configoption1' ] );
    $serviceID = $params[ 'serviceid' ];

    $query = "SELECT
                    onapp_user_id
                FROM
                    tblonappusers
                WHERE
                    service_id = $serviceID";

    $result = full_query( $query );
    //$OnAppUserID = mysql_result( $result, 0 );
    $OnAppUserIDArr = mysql_fetch_assoc( $result );
    $OnAppUserID = $OnAppUserIDArr['onapp_user_id'];

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

    sendmessage( $_LANG[ 'onappusersupgradeaccount' ], $serviceID );

    return 'success';
}

function onappusers_ClientAreaCustomButtonArray() {
    global $_LANG;
    $buttons = array(
        $_LANG[ 'onappusersgeneratenewpassword' ] => 'GeneratePassword',
    );

    return $buttons;
}

function onappusers_GeneratePassword( $params ) {
    global $_LANG;

    $serviceID = $params[ 'serviceid' ];
    $password = OnApp_UserModule::generatePassword();

    $query = "SELECT
                    onapp_user_id
                FROM
                    tblonappusers
                WHERE
                    service_id = $serviceID";

    $result = full_query( $query );
    //$OnAppUserID = mysql_result( $result, 0 );
    $OnAppUserIDArr = mysql_fetch_assoc( $result );
    $OnAppUserID = $OnAppUserIDArr['onapp_user_id'];

    $module = new OnApp_UserModule( $params );
    $OnAppUser = $module->getOnAppObject( 'OnApp_User' );
    $OnAppUser->_id = $OnAppUserID;
    $OnAppUser->_password = $password;
    $OnAppUser->save();

    if( ! is_null( $OnAppUser->error ) ) {
        $errorMsg = $_LANG[ 'onappuserserruserupgrade' ] . ':<br/>';
        $errorMsg .= $OnAppUser->getErrorsAsString( '<br/>' );

        return $errorMsg;
    }

    // Save OnApp login and password
    full_query(
        "UPDATE
                tblhosting
            SET
                password = '" . encrypt( $password ) . "'
            WHERE
                id = '$serviceID'"
    );

    sendmessage( $_LANG[ 'onappuserschangeaccountpassword' ], $serviceID );

    return 'success';
}

function loadLang( $lang = null ) {
    global $_LANG, $CONFIG;

    $currentDir = getcwd();
    chdir( dirname( __FILE__ ) . '/lang/' );
    $availableLangs = glob( '*.txt' );

    if( empty( $lang ) ) {
        $language = isset( $_SESSION[ 'Language' ] ) ? $_SESSION[ 'Language' ] : $CONFIG[ 'Language' ];
    }
    else {
        $language = $lang;
    }
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

        $result = array();
        foreach ($_LANG as $key => $value){
            $result[$key] = utf8_encode($value);
        }
    
    return json_encode( $result );
}

function parseLang( &$html ) {
    $html = preg_replace_callback(
        '#{\$LANG.(.*)}#U',
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

    $html = injectServerRow( $params );
    if( $GLOBALS[ 'CONFIG' ][ 'Template' ] == 'six' ) {
        $html .= file_get_contents( dirname( __FILE__ ) . '/includes/html/clientarea.6.html' );
    }
    else {
        $html .= file_get_contents( dirname( __FILE__ ) . '/includes/html/clientarea.5.html' );
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
    $key = md5( uniqid( rand( 1, 999999 ), true ) );

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
        $key . md5( uniqid( rand( 1, 999999 ), true ) ),
        base64_encode( base64_encode( $crypttext ) )
    );

    $html = file_get_contents( __DIR__ . '/includes/html/serverData.html' );
    $html = str_replace( '{###}', md5( uniqid( rand( 1, 999999 ), true ) ), $html );
    $html .= '<script type="text/javascript">'
        . 'var SERVER = "' . $server . '";'
        . 'var injTarget = "' . $params[ 'username' ] . ' / ' . $params[ 'password' ] . '";'
        . '</script>';

    return $html;
}

function onappusers_AdminLink( $params ) {
    global $_LANG;
    $form = '<form target="_blank" action="http' . ( $params[ 'serversecure' ] == 'on' ? 's' : '' ) . '://' . ( empty( $params[ 'serverhostname' ] ) ? $params[ 'serverip' ] : $params[ 'serverhostname' ] ) . '/users/sign_in" method="post">
                  <input type="hidden" name="user[login]" value="' . $params[ 'serverusername' ] . '" />
                  <input type="hidden" name="user[password]" value="' . $params[ 'serverpassword' ] . '" />
                  <input type="hidden" name="commit" value="Sign In" />
                  <input type="submit" value="' . $_LANG[ 'onappuserslogintocp' ] . '" class="btn btn-default" />
               </form>';

    return $form;
}

function onappusers_OutstandingDetails( $params = '' ) {
    $data = json_encode( OnApp_UserModule::getAmount( $params ) );
    exit( $data );
}

/*function onappusers_AdminCustomButtonArray() {
    if(isset($_GET['userid']) && isset($_GET['id'])){
        OnApp_UserModule::checkIfServiceWasMoved($_GET['userid'], $_GET['id']);
    }
    return array();
}*/

class OnApp_UserModule {
    private $server;

    public function __construct( $params ) {
        $this->server = new stdClass;
        if( $params[ 'serversecure' ] == 'on' ) {
            $this->server->ip = 'https://';
        }
        else {
            $this->server->ip = 'http://';
        }
        $this->server->ip .= empty( $params[ 'serverip' ] ) ? $params[ 'serverhostname' ] : $params[ 'serverip' ];
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
        if( $this->getAPIVersion() > 5.1 ){
            $data = $this->getOnAppObject( 'OnApp_BillingUser' )->getList();
        } else {
            $data = $this->getOnAppObject( 'OnApp_BillingPlan' )->getList();
        }

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

    public static function getAmount( array $params ) {
        if( $_GET[ 'tz_offset' ] != 0 ) {
            $dateFrom = date( 'Y-m-d H:i', strtotime( $_GET[ 'start' ] ) + ( $_GET[ 'tz_offset' ] * 60 ) );
            $dateTill = date( 'Y-m-d H:i', strtotime( $_GET[ 'end' ] ) + ( $_GET[ 'tz_offset' ] * 60 ) );
        }
        else {
            $dateFrom = $_GET[ 'start' ];
            $dateTill = $_GET[ 'end' ];
        }
        $date = array(
            'period[startdate]' => $dateFrom,
            'period[enddate]'   => $dateTill,
        );

        $data = self::getResourcesData( $params, $date );

        if( ! $data ) {
            return false;
        }

        $sql = 'SELECT
                    `code`,
                    `rate`
                FROM
                    `tblcurrencies`
                WHERE
                    `id` = ' . $params[ 'clientsdetails' ][ 'currency' ];
        $rate = mysql_fetch_assoc( full_query( $sql ) );

        $data = $data->user_stat;
        $unset = array(
            'vm_stats',
            'stat_time',
            'user_resources_cost',
            'user_id',
        );
        foreach( $data as $key => &$value ) {
            if( in_array( $key, $unset ) ) {
                unset( $data->$key );
            }
            else {
                $data->$key *= $rate[ 'rate' ];
            }
        }
        $data->currency_code = $rate[ 'code' ];

        return $data;
    }

    private static function getResourcesData( $params, $date ) {
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

        if( $params[ 'serversecure' ] == 'on' ) {
            $serverAddr = 'https://';
        }
        else {
            $serverAddr = 'http://';
        }
        $serverAddr .= empty( $params[ 'serverhostname' ] ) ? $params[ 'serverip' ] : $params[ 'serverhostname' ];

        $date = http_build_query( $date );

        $url = $serverAddr . '/users/' . $user[ 'onapp_user_id' ] . '/user_statistics.json?' . $date;
        $data = self::sendRequest( $url, $params[ 'serverusername' ], $params[ 'serverpassword' ] );

        if( $data ) {
            return json_decode( $data );
        }
        else {
            return false;
        }
    }

    private static function sendRequest( $url, $user, $password ) {
        require_once __DIR__ . '/includes/php/CURL.php';

        $curl = new CURL();
        $curl->addOption( CURLOPT_USERPWD, $user . ':' . $password );
        $curl->addOption( CURLOPT_HTTPHEADER, array( 'Accept: application/json', 'Content-type: application/json' ) );
        $curl->addOption( CURLOPT_HEADER, true );
        $data = $curl->get( $url );

        if( $curl->getRequestInfo( 'http_code' ) != 200 ) {
            return false;
        }
        else {
            return $data;
        }
    }

    public static function generatePassword() {
        return substr( str_shuffle( '~!@$%^&*(){}|0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ' ), 0, 20 );
    }

    public static function checkIfServiceWasMoved($userID, $serviceID) {
        $serviceID = (int)$serviceID;
        $userID = (int)$userID;

        if($serviceID <= 0 || $userID <= 0){
            return false;
        }

        $query = "SELECT * FROM tblonappusers WHERE service_id = " . $serviceID;

        $result = full_query( $query );
        if( !$result ) {
            return false;
        }
        if( mysql_num_rows( $result ) !== 1 ) {
            return false;
        }

        $onappuser = mysql_fetch_assoc( $result );
        if(!$onappuser) {
            return false;
        }

        if($userID !== (int)$onappuser['client_id']){
            $sql = "UPDATE tblonappusers SET client_id = " . $userID . " WHERE service_id = " . $serviceID;
            full_query($sql);
            return true;
        }

        return false;
    }

    private function getWrapperVersion(){
        $pathToWrapper = realpath( ROOTDIR ) . '/includes/wrapper/';
        $version = file_get_contents( $pathToWrapper.'version.txt' );

        return $version;
    }

    private function getAPIVersion() {
        $obj = new OnApp_Factory( $this->server->ip, $this->server->user, $this->server->pass);
        $apiVersion = $obj->getAPIVersion();
        return array(
            'version' => trim($apiVersion),
            'message' => trim($obj->getErrorsAsString( ', ' )),
        );
    }

    public function checkWrapperVersion(){
        $wrapperVersion = trim($this->getWrapperVersion());

        $apiVersionArr = $this->getAPIVersion();
        $apiVersion = $apiVersionArr['version'];

        $result = array();
        $result['apiMessage'] = $apiVersionArr['message'];
        $result['wrapperVersion'] = $wrapperVersion;
        $result['apiVersion'] = $apiVersion;
        if(($wrapperVersion == '')||($apiVersion == '')){
            $result['status'] = false;
            return $result;
        }

        $wrapperVersionAr = preg_split( '/[.,]/', $wrapperVersion, NULL, PREG_SPLIT_NO_EMPTY );
        if((count($wrapperVersionAr) == 1)&&($wrapperVersionAr[0] == '')){
            $result['status'] = false;
            return $result;
        }

        $apiVersionAr     = preg_split( '/[.,]/', $apiVersion, NULL, PREG_SPLIT_NO_EMPTY );
        if((count($apiVersionAr) == 1)&&($apiVersionAr[0] == '')){
            $result['status'] = false;
            return $result;
        }

        $result['status'] = true;
        foreach ( $apiVersionAr as $apiVersionKey => $apiVersionValue ) {
            if ( ! isset( $wrapperVersionAr[ $apiVersionKey ] ) ) {
                $result['status'] = false;
                break;
            }

            $apiVersionValue     = (int) $apiVersionValue;
            $wrapperVersionValue = (int) $wrapperVersionAr[ $apiVersionKey ];

            if ( $apiVersionValue == $wrapperVersionValue ) {
                continue;
            }

            $result['status'] = ( $wrapperVersionAr[ $apiVersionKey ] > $apiVersionValue );
            break;
        }

        return $result;
    }
}
