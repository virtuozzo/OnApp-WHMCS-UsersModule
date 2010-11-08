<?php
define('ONAPPUSERS_CREATE_ACCOUNT_TMPL_NAME', 'OnApp account created');
load_lang();

function onappusers_ConfigOptions() {
    // Should return an array of the module options for each product - maximum of 24
    $configarray = array();
    
    if (($res = create_table()) !== true) {
        return array(
            "<b style='color:red;'>".$res."</b>" => array()
        );
    }
    return $configarray;
}

function onappusers_CreateAccount($params) {
    global $CONFIG, $_LANG;
    $ONAPP_DEFAULT_ROLE  = 2;
    $ONAPP_DEFAULT_GROUP = 1;

    $serviceid = $params["serviceid"]; // Unique ID of the product/service in the WHMCS Database
    $pid = $params["pid"]; // Product/Service ID
    $producttype = $params["producttype"]; // Product Type: hostingaccount, reselleraccount, server or other
    $domain = $params["domain"];
    $username = $params["username"];
    $password = $params["password"];
    $clientsdetails = $params["clientsdetails"]; // Array of clients details - firstname, lastname, email, country, etc...
    $customfields = $params["customfields"]; // Array of custom field values for the product
    $configoptions = $params["configoptions"]; // Array of configurable option values for the product

    // Additional variables if the product/service is linked to a server
    $server = $params["server"]; // True if linked to a server
    $server_id = $params["serverid"];
    $server_ip = $params["serverip"];
    $server_username = $params["serverusername"];
    $server_password = $params["serverpassword"];

    // Save hosting username 
    if (!$username) {
        $username = $clientsdetails['email'];
        full_query("UPDATE
                tblhosting
            SET
                username = '$username'
            WHERE
                id = '$serviceid'");
    }

    if (!$password) {
        return $_LANG['onappuserserrusercreate']."<br/>\n".
            $_LANG['onappuserserrpwdnotset'];
    }

    /*********************************/
    /*      Create OnApp user        */
    $onapp_user = get_onapp_object('ONAPP_User', $server_ip, $server_username, $server_password);

    $onapp_user->_email      = $clientsdetails['email'];
    $onapp_user->_password   = $password;
    $onapp_user->_login      = $username;
    $onapp_user->_first_name = $clientsdetails['firstname'];
    $onapp_user->_last_name  = $clientsdetails['lastname'];

    $onapp_user->_group_id   = $ONAPP_DEFAULT_GROUP;
    $onapp_user->_role_ids   = array(
        'attributesArray' => array(
            'type' => 'array'
        ),
        'role-id' => $ONAPP_DEFAULT_ROLE
    );

    $onapp_user->save();

    if (isset($onapp_user->error)) {
        $error_msg = $_LANG['onappuserserrusercreate'].":<br/>\n";
        $error_msg .= is_array($onapp_user->error) ?
            implode("\n<br/>", $onapp_user->error) :
            $onapp_user->error;
        return $error_msg;
    }

    if (isset($onapp_user->_obj->error)) {
        $error_msg = $_LANG['onappuserserrusercreate'].":<br/>\n";
        $error_msg .= is_array($onapp_user->_obj->error) ?
            implode("\n<br/>", $onapp_user->_obj->error) :
            $onapp_user->_obj->error;
        return $error_msg;
    }

    if ( is_null($onapp_user->_obj->_id) ) {
        return  $_LANG['onappuserserrusercreate'];
    }
    /*    End Create OnApp user      */
    /*********************************/

    // Save user data in whmcs db
    $res_insert = insert_query('tblonappusers', array(
        'server_id'     => $server_id,
        'client_id'     => $clientsdetails['userid'],
        'onapp_user_id' => $onapp_user->_obj->_id,
        'password'      => $password,
        'email'         => $clientsdetails['email']
    ));

    sendmessage(ONAPPUSERS_CREATE_ACCOUNT_TMPL_NAME, $serviceid);

    return 'success';
}

function onappusers_SuspendAccount($params) {
    $client_id       = $params['clientsdetails']['userid'];
    $server_id       = $params['serverid'];
    $server_ip       = $params['serverip'];
    $server_username = $params['serverusername'];
    $server_password = $params['serverpassword'];

    $query = "SELECT
            onapp_user_id
        FROM
            tblonappusers
        WHERE
            server_id = $server_id
            AND client_id = $client_id";

    $result = full_query($query);
    if ($result) {
        $onapp_user_id = mysql_result($result, 0);
    }
    if (!$onapp_user_id) {
        return sprintf($_LANG['onappuserserrassociateuser'], $client_id, $server_id);
    }

    $onapp_user = get_onapp_object('ONAPP_User', $server_ip, $server_username, $server_password);

    $onapp_user->_id = $onapp_user_id;

    $onapp_user->suspend();

    if (isset($onapp_user->error)) {
        $error_msg = $_LANG['onappuserserrusersuspend'].":<br/>\n";
        $error_msg .= is_array($onapp_user->error) ?
            implode("\n<br/>", $onapp_user->error) :
            $onapp_user->error;
        return $error_msg;
    }

    return 'success';
}

function onappusers_UnsuspendAccount($params) {
    $client_id       = $params['clientsdetails']['userid'];
    $server_id       = $params['serverid'];
    $server_ip       = $params['serverip'];
    $server_username = $params['serverusername'];
    $server_password = $params['serverpassword'];

    $query = "SELECT
            onapp_user_id
        FROM
            tblonappusers
        WHERE
            server_id = $server_id
            AND client_id = $client_id";

    $result = full_query($query);
    if ($result) {
        $onapp_user_id = mysql_result($result, 0);
    }
    if (!$onapp_user_id) {
        return sprintf($_LANG['onappuserserrassociateuser'], $client_id, $server_id);
    }

    $onapp_user = get_onapp_object('ONAPP_User', $server_ip, $server_username, $server_password);

    $onapp_user->_id = $onapp_user_id;

    $onapp_user->activate_user();

    if (isset($onapp_user->error)) {
        $error_msg = $_LANG['onappuserserruserunsuspend'].":<br/>\n";
        $error_msg .= is_array($onapp_user->error) ?
            implode("\n<br/>", $onapp_user->error) :
            $onapp_user->error;
        return $error_msg;
    }

    return 'success';
}

/**
 * Create table for user data
 * 
 * @return mixed Return true on success, Otherwise error string.
 */
function create_table() {
    global $_LANG;

    $query = 'CREATE TABLE IF NOT EXISTS `tblonappusers` (
            `server_id` int(11) NOT NULL,
            `client_id` int(11) NOT NULL,
            `onapp_user_id` int(11) NOT NULL,
            `password` text NOT NULL,
            `email` text NOT NULL,
            PRIMARY KEY (`server_id`, `client_id`),
            KEY `client_id` (`client_id`)
        ) ENGINE=InnoDB;';
    if (!full_query($query, $whmcsmysql)) {
        return sprintf($_LANG['onappuserserrtablecreate'], 'onappclients');
    }

    // Add e-mail templates
    $where = array();
    $where['type'] = 'product';
    $where['name'] = ONAPPUSERS_CREATE_ACCOUNT_TMPL_NAME;
    if (!mysql_num_rows(select_query('tblemailtemplates', 'id', $where))) {
        $insert_fields = array();
        $insert_fields['type'] = 'product';
        $insert_fields['name'] = ONAPPUSERS_CREATE_ACCOUNT_TMPL_NAME;
        $insert_fields['subject'] = 'OnApp account has been created';
        $insert_fields['message'] = '<p>Dear {$client_name}</p>'.
            '<p>Your OnApp account has been created<br />'.
            'login: {$service_username}<br />'.
            'password: {$service_password}</p>'.
            '<p></p>To login, visit http://{$service_server_ip}';
        $insert_fields['plaintext'] = 0;
        if (!insert_query('tblemailtemplates', $insert_fields)) {
            return sprintf($_LANG['onappuserserrtmpladd'], ONAPPUSERS_CREATE_ACCOUNT_TMPL_NAME);
        }
    }

    return true;
}

/**
 * Get an instance of specified wrapper class
 * and autorize it on OnApp server;
 * 
 * @param $class string 
 * @param $server_ip string
 * @param $email     string
 * @param $apikey    string
 * 
 * @return object
 */
function get_onapp_object($class, $server_ip, $email, $apikey)  {
    $required_file = str_replace('ONAPP_', '', $class).'.php';
    $required_path = file_exists(ROOTDIR.'/modules/servers/onapp') ?
        ROOTDIR.'/modules/servers/onapp/wrapper/':
        'includes/wrapper/';
    require_once $required_path.$required_file;

    $obj = new $class;

    $obj->auth($server_ip, $email, $apikey);

    return $obj;
}

/**
 * Load $_LANG from language file
 */
function load_lang() {
    $dh = opendir (dirname(__FILE__).'/lang/');

    while (false !== $file2 = readdir ($dh)) {
        if (!is_dir ('' . 'lang/' . $file2) ) {
            $pieces = explode ('.', $file2);
            if ($pieces[1] == 'txt') {
                $arrayoflanguagefiles[] = $pieces[0];
                continue;
            }
            continue;
        }
    };

    closedir ($dh);

    $language = $_SESSION['Language'];

    if ( ! in_array ($language, $arrayoflanguagefiles) )
        $language =  "English";

    ob_start ();
    include dirname(__FILE__) . "/lang/$language.txt";
    $templang = ob_get_contents ();
    ob_end_clean ();
    eval ($templang);
}