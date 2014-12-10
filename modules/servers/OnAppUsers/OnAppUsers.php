<?php

loadLang();

if( ! defined( 'ONAPP_WRAPPER_INIT' ) ) {
    define( 'ONAPP_WRAPPER_INIT', ROOTDIR . '/includes/wrapper/OnAppInit.php' );
}

if( file_exists( ONAPP_WRAPPER_INIT ) ) {
    require_once ONAPP_WRAPPER_INIT;
}
else {
    ob_end_clean();
    echo '<pre>', $_LANG[ 'OnApp_Users_Error_WrapperNotFound' ] . realpath( ROOTDIR ) . '/includes/wrapper';
    exit;
}

function OnAppUsers_ConfigOptions( $params ) {
    global $_LANG;
    $ramTMP = ini_get( 'memory_limit' );
    ini_set( 'memory_limit', '512M' );

    $js = '<script type="text/javascript" src="../modules/servers/OnAppUsers/includes/js/adminArea.js"></script>';
    $js .= '<script type="text/javascript" src="../modules/servers/OnAppUsers/includes/js/tz.js"></script>';

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

    $res         = full_query( $sql );
    $serversData = array();
    if( mysql_num_rows( $res ) == 0 ) {
        $serversData[ 'NoServers' ] = sprintf( $_LANG[ 'OnApp_Users_Error_Admin_Holder' ], $_LANG[ 'OnApp_Users_Error_Admin_NoServers' ] );
    }
    else {
        while( $onappConfig = mysql_fetch_assoc( $res ) ) {
            # Error if server adress (IP and hostname) not set
            if( empty( $onappConfig[ 'serverip' ] ) && empty( $onappConfig[ 'serverhostname' ] ) ) {
                $msg = sprintf( $_LANG[ 'OnApp_Users_Error_Admin_CantFoundHostAddress' ] );

                $data[ 'Name' ]                      = $onappConfig[ 'name' ];
                $data[ 'NoAddress' ]                 = sprintf( $_LANG[ 'OnApp_Users_Error_Admin_Holder' ], $msg );
                $serversData[ $onappConfig[ 'id' ] ] = $data;
                continue;
            }
            $onappConfig[ 'serverpassword' ] = decrypt( $onappConfig[ 'serverpassword' ] );

            $data   = array();
            $module = new OnApp_UserModule( $onappConfig );

            # handle billing plans per server
            $data[ 'BillingPlans' ] = $data[ 'SuspendedBillingPlans' ] = $module->getBillingPlans();
            if( empty( $data[ 'BillingPlans' ] ) ) {
                $msg                    = sprintf( $_LANG[ 'OnApp_Users_Error_Admin_NoBillingPlans' ] );
                $data[ 'BillingPlans' ] = $data[ 'SuspendedBillingPlans' ] = sprintf( $_LANG[ 'OnApp_Users_Error_Admin_Holder' ], $msg );
            }

            # handle user roles per server
            $data[ 'Roles' ] = $module->getRoles();
            if( empty( $data[ 'Roles' ] ) ) {
                $msg             = sprintf( $_LANG[ 'OnApp_Users_Error_Admin_NoRoles' ] );
                $data[ 'Roles' ] = sprintf( $_LANG[ 'OnApp_Users_Error_Admin_Holder' ], $msg );
            }

            # handle user groups per server
            $data[ 'UserGroups' ] = $module->getUserGroups();
            if( empty( $data[ 'UserGroups' ] ) ) {
                $msg                  = sprintf( $_LANG[ 'OnApp_Users_Error_Admin_NoUserGroups' ] );
                $data[ 'UserGroups' ] = sprintf( $_LANG[ 'OnApp_Users_Error_Admin_Holder' ], $msg );
            }

            # handle locales per server
            $data[ 'Locales' ]                   = $module->getLocales();
            $data[ 'Name' ]                      = $onappConfig[ 'name' ];
            $serversData[ $onappConfig[ 'id' ] ] = $data;
            unset( $data );
        }

        $sql = 'SELECT
                    prod.`configoption1` AS `options`,
                    prod.`servergroup` AS `group`
                FROM
                    `tblproducts` AS `prod`
                WHERE
                    prod.`id` = :id';
        $sql                    = str_replace( ':id', (int)$_GET[ 'id' ], $sql );
        $results                = full_query( $sql );
        $results                = mysql_fetch_assoc( $results );
        $results[ 'options' ]   = htmlspecialchars_decode( $results[ 'options' ] );
        $serversData[ 'Group' ] = $results[ 'group' ];
        if( !empty( $results[ 'options' ] ) ) {
            $results[ 'options' ] = json_decode( $results[ 'options' ], true );
            $serversData += $results[ 'options' ];
        }
    }

    $js .= '<script type="text/javascript">'
        . 'var ServersData = ' . json_encode( $serversData ) . ';'
        . 'var ONAPP_LANG = ' . getJSLang() . ';'
        . '</script>';

    if( isset( $_GET[ 'servergroup' ] ) ) {
        ob_end_clean();
        exit( json_encode( $serversData ) );
    }

    # configoption1 = settings
    # configoption2 = SuspendDays
    # configoption3 = TerminateDays
    # configoption4 = DueDateGap
    # configoption5 = Hide zero amount entries in client area
    $configArray = array(
        '' => array(
            'Description' => $js
        ),
        'suspendDays' => array(
            'Type'         => 'text',
            'Size'         => '2',
            'Default'      => '7',
        ),
        'terminateDays' => array(
            'Type'         => 'text',
            'Size'         => '1',
            'Default'      => '7',
        ),
        'dueDateGap' => array(
            'Type'         => 'text',
            'Size'         => '1',
            'Default'      => '0',
        ),
        'hideZeroEntries' => array(
            'Type'         => 'yesno',
        ),
    );
    ini_set( 'memory_limit', $ramTMP );

    return $configArray;
}

function OnAppUsers_CreateAccount( $params ) {
    global $CONFIG, $_LANG;

    $clientsDetails = $params[ 'clientsdetails' ];
    $serviceID      = $params[ 'serviceid' ];
    $productID      = $params[ 'packageid' ];
    $userName       = substr( $clientsDetails[ 'email' ], 0, 40 );
    $password       = OnApp_UserModule::generatePassword();

    $module                 = new OnApp_UserModule( $params );
    $OnAppUser              = $module->getOnAppObject( 'OnApp_User' );
    $OnAppUser->_email      = $clientsDetails[ 'email' ];
    $OnAppUser->_password   = $OnAppUser->_password_confirmation = $password;
    $OnAppUser->_login      = $userName;
    $OnAppUser->_first_name = $clientsDetails[ 'firstname' ];
    $OnAppUser->_last_name  = $clientsDetails[ 'lastname' ];

    $params[ 'configoption1' ] = html_entity_decode( $params[ 'configoption1' ] );
    $params[ 'configoption1' ] = json_decode( $params[ 'configoption1' ], true );
    # Assign billing group/plan to user
    $billingPlanID               = $params[ 'configoption1' ][ 'SelectedPlans' ][ $params[ 'serverid' ] ];
    $OnAppUser->_billing_plan_id = $billingPlanID;

    $tmp                  = array();
    $OnAppUser->_role_ids = array_merge( $tmp, $params[ 'configoption1' ][ 'SelectedRoles' ][ $params[ 'serverid' ] ] );

    # Assign TZ to user
    $OnAppUser->_time_zone = $params[ 'configoption1' ][ 'SelectedTZs' ][ $params[ 'serverid' ] ];

    # Assign user group to user
    $OnAppUser->_user_group_id = $params[ 'configoption1' ][ 'SelectedUserGroups' ][ $params[ 'serverid' ] ];

    # Assign locale to user
    if( isset( $params[ 'configoption1' ][ 'SelectedLocales' ][ $params[ 'serverid' ] ] ) ) {
        $OnAppUser->_locale = $params[ 'configoption1' ][ 'SelectedLocales' ][ $params[ 'serverid' ] ];
    }

    $OnAppUser->save();
    if( !is_null( $OnAppUser->getErrorsAsArray() ) ) {
        $errorMsg = $_LANG[ 'OnApp_Users_Error_Admin_CreateUser' ] . ': ';
        $errorMsg .= $OnAppUser->getErrorsAsString( ', ' );

        return $errorMsg;
    }

    if( !is_null( $OnAppUser->_obj->getErrorsAsArray() ) ) {
        $errorMsg = $_LANG[ 'OnApp_Users_Error_Admin_CreateUser' ] . ': ';
        $errorMsg .= $OnAppUser->_obj->getErrorsAsString( ', ' );

        return $errorMsg;
    }

    if( is_null( $OnAppUser->_obj->_id ) ) {
        return $_LANG[ 'OnApp_Users_Error_Admin_CreateUser' ];
    }

    $params[ 'customfields' ][ 'OnApp user ID' ] = $OnAppUser->_obj->_id;

    # Save user link in custom field
    $sql = 'UPDATE
                `tblcustomfieldsvalues`
            JOIN
                tblcustomfields
                ON tblcustomfields.`id` = tblcustomfieldsvalues.`fieldid`
            SET
                tblcustomfieldsvalues.`value` = :userID
            WHERE
                tblcustomfields.`relid` = :productID
                AND `fieldname` = "OnApp user ID"
                AND tblcustomfieldsvalues.`relid` = :serviceID';
    $sql = str_replace( ':serviceID', $serviceID, $sql );
    $sql = str_replace( ':productID', $productID, $sql );
    $sql = str_replace( ':userID', $OnAppUser->_obj->_id, $sql );
    full_query( $sql );

    # Save OnApp login and password
    $table = 'tblhosting';
    $update = array(
        'password' => encrypt( $password ),
        'username' => $userName,
    );
    $where = array( 'id' => $serviceID );
    update_query( $table, $update, $where );

    sendmessage( OnApp_UserModule::getEmailTemplates( 'CreateAccount' ), $serviceID );

    return 'success';
}

function OnAppUsers_TerminateAccount( $params ) {
    global $_LANG;

    $serviceID   = $params[ 'serviceid' ];
    $productID   = $params[ 'packageid' ];
    $OnAppUserID = $params[ 'customfields' ][ 'OnApp user ID' ];

    $module    = new OnApp_UserModule( $params );
    $OnAppUser = $module->getOnAppObject( 'OnApp_User' );

    $OnAppUser->_id = $OnAppUserID;
    $OnAppUser->delete( true );

    if( ! empty( $OnAppUser->error ) ) {
        $errorMsg = $_LANG[ 'OnApp_Users_Error_Admin_TerminateUser' ] . ': ';
        $errorMsg .= $OnAppUser->getErrorsAsString( ', ' );

        return $errorMsg;
    }

    # Delete user link from custom field
    $sql = 'UPDATE
                tblcustomfieldsvalues
            JOIN
                tblcustomfields
                ON tblcustomfields.`id` = tblcustomfieldsvalues.`fieldid`
            SET
                tblcustomfieldsvalues.`value` = NULL
            WHERE
                tblcustomfields.`relid` = :productID
                AND tblcustomfields.`fieldname` = "OnApp user ID"
                AND tblcustomfieldsvalues.`relid` = :serviceID';
    $sql = str_replace( ':serviceID', $serviceID, $sql );
    $sql = str_replace( ':productID', $productID, $sql );
    full_query( $sql );

    sendmessage( OnApp_UserModule::getEmailTemplates( 'TerminateAccount' ), $serviceID );

    return 'success';
}

function OnAppUsers_SuspendAccount( $params ) {
    global $_LANG;

    $serverID    = $params[ 'serverid' ];
    $serviceID   = $params[ 'serviceid' ];
    $billingPlan = json_decode( $params[ 'configoption1' ] );
    $billingPlan = $billingPlan->SelectedSuspendedPlans->$serverID;
    $OnAppUserID = $params[ 'customfields' ][ 'OnApp user ID' ];

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

    if( !is_null( $OnAppUser->error ) ) {
        $errorMsg = $_LANG[ 'OnApp_Users_Error_Admin_SuspendUser' ] . ':<br/>';
        $errorMsg .= $OnAppUser->getErrorsAsString( '<br/>' );

        return $errorMsg;
    }

    sendmessage( OnApp_UserModule::getEmailTemplates( 'SuspendAccount' ), $serviceID );

    return 'success';
}

function OnAppUsers_UnsuspendAccount( $params ) {
    global $_LANG;

    $serverID    = $params[ 'serverid' ];
    $serviceID   = $params[ 'serviceid' ];
    $billingPlan = json_decode( $params[ 'configoption1' ] );
    $billingPlan = $billingPlan->SelectedPlans->$serverID;
    $OnAppUserID = $params[ 'customfields' ][ 'OnApp user ID' ];

    $module    = new OnApp_UserModule( $params );
    $OnAppUser = $module->getOnAppObject( 'OnApp_User' );
    $unset     = array( 'time_zone', 'user_group_id', 'locale' );
    $OnAppUser->unsetFields( $unset );

    $OnAppUser->_id              = $OnAppUserID;
    $OnAppUser->_billing_plan_id = $billingPlan;
    $OnAppUser->save();
    $OnAppUser->activate_user();

    if( !is_null( $OnAppUser->error ) ) {
        $errorMsg = $_LANG[ 'OnApp_Users_Error_Admin_UnsuspendUser' ] . ':<br/>';
        $errorMsg .= $OnAppUser->getErrorsAsString( '<br/>' );

        return $errorMsg;
    }

    sendmessage( OnApp_UserModule::getEmailTemplates( 'UnsuspendAccount' ), $serviceID );

    return 'success';
}

function OnAppUsers_ChangePackage( $params ) {
    global $_LANG;

    if( $params[ 'action' ] !== 'upgrade' ) {
        return;
    }

    $config    = json_decode( $params[ 'configoption1' ] );
    $serviceID = $params[ 'serviceid' ];
    $OnAppUserID = $params[ 'customfields' ][ 'OnApp user ID' ];

    $module                      = new OnApp_UserModule( $params );
    $OnAppUser                   = $module->getOnAppObject( 'OnApp_User' );
    $OnAppUser->_id              = $OnAppUserID;
    $OnAppUser->_time_zone       = $config->SelectedTZs->$params[ 'serverid' ];
    $OnAppUser->_locale          = $config->SelectedLocales->$params[ 'serverid' ];
    $OnAppUser->_billing_plan_id = $config->SelectedPlans->$params[ 'serverid' ];
    $OnAppUser->_user_group_id   = $config->SelectedUserGroups->$params[ 'serverid' ];
    $OnAppUser->save();

    if( !is_null( $OnAppUser->error ) ) {
        $errorMsg = $_LANG[ 'OnApp_Users_Error_Admin_UpgradeUser' ] . ':<br/>';
        $errorMsg .= $OnAppUser->getErrorsAsString( '<br/>' );

        return $errorMsg;
    }

    sendmessage( OnApp_UserModule::getEmailTemplates( 'UpgradeAccount' ), $serviceID );

    return 'success';
}

function OnAppUsers_ClientAreaCustomButtonArray() {
    global $_LANG;
    $buttons = array(
        $_LANG[ 'OnApp_Users_Client_GenerateNewPassword' ] => 'GeneratePassword',
    );

    return $buttons;
}

function OnAppUsers_GeneratePassword( $params ) {
    $serviceID   = $params[ 'serviceid' ];
    $password    = OnApp_UserModule::generatePassword();
    $OnAppUserID = $params[ 'customfields' ][ 'OnApp user ID' ];

    $module              = new OnApp_UserModule( $params );
    $OnAppUser           = $module->getOnAppObject( 'OnApp_User' );
    $OnAppUser->id       = $OnAppUserID;
    $OnAppUser->password = $password;
    $OnAppUser->save();

    if( !is_null( $OnAppUser->error ) ) {
        $errorMsg = $OnAppUser->getErrorsAsString( '<br/>' );
        exit( $errorMsg );
    }

    # Save OnApp login and password
    $table  = 'tblhosting';
    $update = array( 'password' => encrypt( $password ) );
    $where  = array( 'id' => $serviceID );
    update_query( $table, $update, $where );

    sendmessage( OnApp_UserModule::getEmailTemplates( 'ChangeAccountPassword' ), $serviceID );

    exit( 'success' );
}

function loadLang( $languageFile = null ) {
    global $_LANG, $CONFIG;
    $languageFileDir = __DIR__ . '/lang/';

    if( is_null( $languageFile ) ) {
        $languageFile = isset( $_SESSION[ 'Language' ] ) ? $_SESSION[ 'Language' ] : $CONFIG[ 'Language' ];
    }
    $languageFile = $languageFileDir . strtolower( $languageFile ) . '.php';

    if( ! file_exists( $languageFile ) ) {
        $languageFile = $languageFileDir . 'english.php';
    }

    require $languageFile;
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

function OnAppUsers_ClientArea( $params = '' ) {
    if( isset( $_GET[ 'getstat' ] ) ) {
        OnAppUsers_OutstandingDetails( $params );
    }

    injectServerRow( $params );
    $html = file_get_contents( dirname( __FILE__ ) . '/includes/html/clientArea.html' );

    parseLang( $html );
    $html .= '<script type="text/javascript">'
        . 'var UID = ' . $params[ 'clientsdetails' ][ 'userid' ] . ';'
        . 'var PID = ' . $params[ 'accountid' ] . ';'
        . 'var LANG = ' . getJSLang() . ';</script>';
    $html = str_replace( '{###}', md5( uniqid( rand( 1, 999999 ), true ) ), $html );

    return $html;
}

function injectServerRow( $params ) {
    $iv_size = mcrypt_get_iv_size( MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB );
    $iv      = mcrypt_create_iv( $iv_size, MCRYPT_RAND );
    $key     = substr( md5( uniqid( rand( 1, 999999 ), true ) ), 0, 27 );

    $server = !empty( $params[ 'serverip' ] ) ? $params[ 'serverip' ] : $params[ 'serverhostname' ];
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

    $crypttext         = mcrypt_encrypt( MCRYPT_RIJNDAEL_256, $key, $data, MCRYPT_MODE_ECB, $iv );
    $_SESSION[ 'utk' ] = array(
        $key . substr( md5( uniqid( rand( 1, 999999 ), true ) ), rand( 0, 26 ), 5 ),
        base64_encode( base64_encode( $crypttext ) )
    );
}

function OnAppUsers_AdminLink( $params ) {
    global $_LANG;
    $form = '<form target="_blank" action="http' . ( $params[ 'serversecure' ] == 'on' ? 's' : '' ) . '://' . ( empty( $params[ 'serverhostname' ] ) ? $params[ 'serverip' ] : $params[ 'serverhostname' ] ) . '/users/sign_in" method="post">
                  <input type="hidden" name="user[login]" value="' . $params[ 'serverusername' ] . '" />
                  <input type="hidden" name="user[password]" value="' . $params[ 'serverpassword' ] . '" />
                  <input type="hidden" name="commit" value="Sign In" />
                  <input type="submit" value="' . $_LANG[ 'OnApp_Users_Admin_LoginToCP' ] . '" />
               </form>';

    return $form;
}

function OnAppUsers_OutstandingDetails( $params = '' ) {
    $data = json_encode( OnApp_UserModule::getAmount( $params ) );
    exit( $data );
}

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

    public static function getEmailTemplates( $name = null ) {
        $tpls = array(
            'CreateAccount'         => 'OnApp account has been created',
            'SuspendAccount'        => 'OnApp account has been suspended',
            'TerminateAccount'      => 'OnApp account has been terminated',
            'UnsuspendAccount'      => 'OnApp account has been unsuspended',
            'UpgradeAccount'        => 'OnApp account has been upgraded',
            'ChangeAccountPassword' => 'OnApp account password has been generated',
        );
        if( $name ) {
            return $tpls[ $name ];
        }
        else {
            return $tpls;
        }
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

        if( !$data ) {
            return false;
        }

        $sql  = 'SELECT
                    `code`,
                    `rate`
                FROM
                    `tblcurrencies`
                WHERE
                    `id` = ' . $params[ 'clientsdetails' ][ 'currency' ];
        $rate = mysql_fetch_assoc( full_query( $sql ) );

        $data  = $data->user_stat;
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
        $data->hideZeroEntries = $params[ 'configoption5' ];

        return $data;
    }

    private static function getResourcesData( $params, $date ) {
        if( $params[ 'serversecure' ] == 'on' ) {
            $serverAddr = 'https://';
        }
        else {
            $serverAddr = 'http://';
        }
        $serverAddr .= empty( $params[ 'serverhostname' ] ) ? $params[ 'serverip' ] : $params[ 'serverhostname' ];

        $date = http_build_query( $date );

        $url  = $serverAddr . '/users/' . $params[ 'customfields' ][ 'OnApp user ID' ] . '/user_statistics.json?' . $date;
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
}