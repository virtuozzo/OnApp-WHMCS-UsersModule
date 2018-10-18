<?php

require_once(__DIR__ . '/includes/php/OnApp_UserModule.php');

OnApp_UserModule::loadLang();

if (file_exists($file = __DIR__ . '/module.sql')) {
    logactivity('OnApp User Module: process SQL file, called from module.');

    $sql = file_get_contents($file);

    $sql = preg_split('/(\r\n\r\n|\n\n)/', $sql);

    foreach ($sql as $qry) {
        full_query($qry);
    }

    unlink($file);

    if (file_exists($file)) {
        chmod($file, 0777);
        unlink($file);
    }

    if (file_exists($file)) {
        logactivity('OnApp User Module: can not delete ' . $file . ', it should be deleted manually');
    }

    // Add e-mail templates
    $moduleMailTemplatesFile = __DIR__ . '/module.mail.php';
    if (file_exists($moduleMailTemplatesFile)) {
        require_once($moduleMailTemplatesFile);
    }
}

function onappusers_ConfigOptions()
{
    global $_LANG;

    if (!file_exists(ONAPP_WRAPPER_INIT)) {
        $configArray = array(
            $_LANG['onappwrappernotfound'] . realpath(ROOTDIR) . '/includes/wrapper' => array()
        );

        return $configArray;
    }

    $js = '<script type="text/javascript" src="../modules/servers/onappusers/includes/js/onappusers.js"></script>';
    $js .= '<script type="text/javascript" src="../modules/servers/onappusers/includes/js/tz.js"></script>';

    $serverGroup = isset($_GET['servergroup']) ? $_GET['servergroup'] : (int) $GLOBALS['servergroup'];
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
                grp.`id` = :servergroup AND 
                srv.`disabled` = 0 AND 
                srv.`type` = \':type\'';
    $sql = str_replace(':servergroup', $serverGroup, $sql);
    $sql = str_replace(':type', OnApp_UserModule::MODULE_NAME, $sql);

    $res = full_query($sql);
    $serversData = array();
    $error = false;
    if (mysql_num_rows($res) == 0) {
        $serversData['NoServers'] = sprintf($_LANG['onappuserserrorholder'], $_LANG['onappuserserrornoserveringroup']);
        $error = true;
    } else {
        while ($onappConfig = mysql_fetch_assoc($res)) {
            if (empty($onappConfig['serverip']) && empty($onappConfig['serverhostname'])) {
                //Error if server address (IP and hostname) not set
                $serversData[$onappConfig['id']] = array(
                    'Name' => $onappConfig['name'],
                    'NoAddress' => sprintf($_LANG['onappuserserrorholder'], sprintf($_LANG['onapperrcantfoundadress'])),
                );
                continue;
            }
            $onappConfig['serverpassword'] = decrypt($onappConfig['serverpassword']);

            $data = array();
            $module = new OnApp_UserModule($onappConfig);

            //compare wrapper version with API

            $compareResult = $module->checkWrapperVersion();

            if (!$compareResult['status']) {
                $error = $_LANG['onappneedupdatewrapper'] . ' (wrapper version: ' . $compareResult['wrapperVersion'] . '; ' . 'api version: ' . $compareResult['apiVersion'] . ')';
                if ($compareResult['apiMessage'] != '') {
                    $error .= '; ' . $compareResult['apiMessage'];
                }
                $configArray = array(
                    $error => array()
                );

                return $configArray;
            }

            $_LANG['custom_wrapperVersion'] = $compareResult['wrapperVersion'];
            $_LANG['custom_apiVersion'] = $compareResult['apiVersion'];
            $_LANG['custom_moduleVersion'] = $module->getModuleVersion();

            // handle billing plans per server
            $data['BillingPlans'] = $data['SuspendedBillingPlans'] = $module->getBillingPlans();
            if (empty($data['BillingPlans'])) {
                $msg = sprintf($_LANG['onappusersnobillingplans']);
                $data['BillingPlans'] = $data['SuspendedBillingPlans'] = sprintf($_LANG['onappuserserrorholder'], $msg);
            }

            //handle user roles per server
            $data['Roles'] = $module->getRoles();
            if (empty($data['Roles'])) {
                $msg = sprintf($_LANG['onappusersnoroles']);
                $data['Roles'] = sprintf($_LANG['onappuserserrorholder'], $msg);
            }

            // handle user groups per server
            $data['UserGroups'] = $module->getUserGroups();
            if (empty($data['UserGroups'])) {
                $msg = sprintf($_LANG['onappusersnousergroups']);
                $data['UserGroups'] = sprintf($_LANG['onappuserserrorholder'], $msg);
            }

            //handle locales per server
            $data['Locales'] = $module->getLocales();

            $data['Name'] = $onappConfig['name'];
            $serversData[$onappConfig['id']] = $data;
            unset($data);
        }

        $sql = 'SELECT
                    prod.`configoption1` AS options,
                    prod.`servergroup` AS `group`
                FROM
                    `tblproducts` AS prod
                WHERE
                    prod.`id` = :id';
        if (!isset($_GET['id'])) {
            preg_match_all('/id=(\d+)/', $_SERVER['HTTP_REFERER'], $matches);
            $sql = str_replace(':id', (int) $matches[1][0], $sql);
        } else {
            $sql = str_replace(':id', (int) $_GET['id'], $sql);
        }

        $results = full_query($sql);
        $results = mysql_fetch_assoc($results);

        $results['options'] = htmlspecialchars_decode($results['options']);
        $serversData['Group'] = $results['group'];

        if (!empty($results['options'])) {
            $results['options'] = json_decode($results['options'], true);
            $serversData += $results['options'];
        }
    }

    $js .= '<script type="text/javascript">'
        . 'var ServersData = ' . json_encode($serversData) . ';'
        . 'var ONAPP_LANG = ' . OnApp_UserModule::getJSLang() . ';'
        . 'buildFields( ServersData );'
        . '</script>';

    if ((!$error) && isset($_GET['servergroup'])) {
        ob_end_clean();
        exit(json_encode($serversData));
    }

    $configArray = array(
        sprintf('') => array(
            'Description' => $js
        ),
    );

    return $configArray;
}

function onappusers_CreateAccount($params)
{
    global $CONFIG, $_LANG;

    if (!file_exists(ONAPP_WRAPPER_INIT)) {
        return $_LANG['onappwrappernotfound'] . realpath(ROOTDIR) . '/includes';
    }

    $clientsDetails = $params['clientsdetails'];
    $serviceID = $params['serviceid'];
    $userName = $params['username'] ? $params['username'] : $clientsDetails['email'];
    $password = OnApp_UserModule::generatePassword();

    if (!$password) {
        return $_LANG['onappuserserrusercreate'] . ': ' . $_LANG['onappuserserrpwdnotset'];
    }

    $module = new OnApp_UserModule($params);

    $OnAppUser = $module->getOnAppObject('OnApp_User');

    $OnAppUser->_email = $clientsDetails['email'];
    $OnAppUser->_password = $OnAppUser->_password_confirmation = $password;
    $OnAppUser->_login = $userName;
    $OnAppUser->_first_name = $clientsDetails['firstname'];
    $OnAppUser->_last_name = $clientsDetails['lastname'];

    // Assign billing group/plan to user
    $billingRow = $module->getBillingFieldName();

    $billingPlanID = $module->getBillingPlanID();
    if (!$billingPlanID) {
        return $module->getLastErrorMessage();
    }

    $OnAppUser->{$billingRow} = $billingPlanID;

    $tmp = array();
    $OnAppUser->_role_ids = array_merge($tmp, $module->getProductParam('SelectedRoles'));

    // Assign TZ to user
    $OnAppUser->_time_zone = $module->getProductParam('SelectedTZs');

    // Assign user group to user
    $OnAppUser->_user_group_id = $module->getProductParam('SelectedUserGroups');

    // Assign locale to user
    $OnAppUser->_locale = $module->getProductParam('SelectedLocales');

    $OnAppUser->save();
    if (!is_null($OnAppUser->getErrorsAsArray())) {
        $errorMsg = $_LANG['onappuserserrusercreate'] . ': ';
        $errorMsg .= $OnAppUser->getErrorsAsString(', ');

        return $errorMsg;
    }

    if (!is_null($OnAppUser->_obj->getErrorsAsArray())) {
        $errorMsg = $_LANG['onappuserserrusercreate'] . ': ';
        $errorMsg .= $OnAppUser->_obj->getErrorsAsString(', ');

        return $errorMsg;
    }

    if (is_null($OnAppUser->_obj->_id)) {
        return $_LANG['onappuserserrusercreate'];
    }

    $module->renameBillingPlanIfCustom($billingPlanID, $OnAppUser->_obj->_id, $userName);

    // Save user link in whmcs db
    insert_query('tblonappusers', array(
        'server_id' => $params['serverid'],
        'client_id' => $clientsDetails['userid'],
        'service_id' => $serviceID,
        'onapp_user_id' => $OnAppUser->_obj->_id,
        'billing_type' => $module->getBillingType(),
        'custom_billing_plan_id' => $module->getCustomBillingPlanIDIfItsNotDefault($billingPlanID),
    ));

    // Save OnApp login and password
    full_query(
        "UPDATE
                tblhosting
            SET
                password = '" . encrypt($password) . "',
                username = '$userName'
            WHERE
                id = '$serviceID'"
    );

    sendmessage($_LANG['onappuserscreateaccount'], $serviceID);

    return 'success';
}

function onappusers_TerminateAccount($params)
{
    global $_LANG;

    if (!file_exists(ONAPP_WRAPPER_INIT)) {
        return $_LANG['onappwrappernotfound'] . realpath(ROOTDIR) . '/includes';
    }

    $serviceID = $params['serviceid'];

    $query = "SELECT
                    custom_billing_plan_id, onapp_user_id
                FROM
                    tblonappusers
                WHERE
                    service_id = $serviceID";

    $result = full_query($query);
    if ($result) {
        $OnAppUserIDArr = mysql_fetch_assoc($result);
        $OnAppUserID = $OnAppUserIDArr['onapp_user_id'];
        $userBillingPlanID = $OnAppUserIDArr['custom_billing_plan_id'];
    }
    if (!$OnAppUserID) {
        return sprintf($_LANG['onappuserserrassociateuserbyserviceid'], $serviceID);
    }

    $module = new OnApp_UserModule($params);
    $OnAppUser = $module->getOnAppObject('OnApp_User');
    $OnAppUser->_id = $OnAppUserID;

    $force = false;
    if ($module->getExtData("forceDelete") == "yes") {
        $force = true;
    }
    $OnAppUser->delete($force);

    if (!empty($OnAppUser->error)) {
        $errorMsg = $_LANG['onappuserserruserdelete'] . ': ';
        $errorMsg .= $OnAppUser->getErrorsAsString(', ');

        return $errorMsg;
    } else {
        $query = 'DELETE FROM
                        tblonappusers
                    WHERE
                        service_id = ' . (int) $serviceID;
        full_query($query);
    }

    $module->deleteCustomBillingPlan($userBillingPlanID);

    sendmessage($_LANG['onappusersterminateaccount'], $serviceID);

    return 'success';
}

function onappusers_SuspendAccount($params)
{
    global $_LANG;

    if (!file_exists(ONAPP_WRAPPER_INIT)) {
        return $_LANG['onappwrappernotfound'] . realpath(ROOTDIR) . '/includes';
    }

    $serviceID = $params['serviceid'];

    $query = "SELECT
                    onapp_user_id
                FROM
                    tblonappusers
                WHERE
                    service_id = $serviceID";

    $result = full_query($query);
    if ($result) {
        $OnAppUserIDArr = mysql_fetch_assoc($result);
        $OnAppUserID = $OnAppUserIDArr['onapp_user_id'];
    }
    if (!$OnAppUserID) {
        return sprintf($_LANG['onappuserserrassociateuserbyserviceid'], $serviceID);
    }

    $module = new OnApp_UserModule($params);

    $suspendedBillingPlan = $module->getDefaultSuspendBillingPlanID();

    $OnAppUser = $module->getOnAppObject('OnApp_User');
    $OnAppUser->_id = $OnAppUserID;
    if ($suspendedBillingPlan) {
        $OnAppUser->unsetFields(array('time_zone', 'user_group_id', 'locale'));
        $billingRow = $module->getBillingFieldName();
        $OnAppUser->{$billingRow} = $suspendedBillingPlan;
        $OnAppUser->save();
    }
    $OnAppUser->suspend();

    if (!is_null($OnAppUser->error)) {
        $errorMsg = $_LANG['onappuserserrusersuspend'] . ':<br/>';
        $errorMsg .= $OnAppUser->getErrorsAsString('<br/>');

        return $errorMsg;
    }

    sendmessage($_LANG['onappuserssuspendaccount'], $serviceID);

    return 'success';
}

function onappusers_UnsuspendAccount($params)
{
    global $_LANG;

    if (!file_exists(ONAPP_WRAPPER_INIT)) {
        return $_LANG['onappwrappernotfound'] . realpath(ROOTDIR) . '/includes';
    }

    $serviceID = $params['serviceid'];

    $query = "SELECT
                    custom_billing_plan_id, onapp_user_id
                FROM
                    tblonappusers
                WHERE
                    service_id = $serviceID";

    $result = full_query($query);
    if ($result) {
        $OnAppUserIDArr = mysql_fetch_assoc($result);
        $onAppUserID = $OnAppUserIDArr['onapp_user_id'];
        $userBillingPlanID = $OnAppUserIDArr['custom_billing_plan_id'];
    }
    if (!$onAppUserID) {
        return sprintf($_LANG['onappuserserrassociateuserbyserviceid'], $serviceID);
    }

    $module = new OnApp_UserModule($params);

    if (!$userBillingPlanID) {
        $userBillingPlanID = $module->getProductParam('SelectedPlans');
    }

    $onAppUser = $module->getOnAppObject('OnApp_User');
    $onAppUser->unsetFields(array('time_zone', 'user_group_id', 'locale'));

    $onAppUser->_id = $onAppUserID;
    $billingRow = $module->getBillingFieldName();
    $onAppUser->{$billingRow} = $userBillingPlanID;
    $onAppUser->save();

    $onAppUser->activate_user();

    if (!is_null($onAppUser->error)) {
        $errorMsg = $_LANG['onappuserserruserunsuspend'] . ':<br/>';
        $errorMsg .= $onAppUser->getErrorsAsString('<br/>');

        return $errorMsg;
    }

    sendmessage($_LANG['onappusersunsuspendaccount'], $serviceID);

    return 'success';
}

function onappusers_ChangePackage($params)
{
    global $_LANG;

    if ($params['action'] !== 'upgrade') {
        return;
    }

    $serviceID = $params['serviceid'];

    $query = "SELECT
                    onapp_user_id
                FROM
                    tblonappusers
                WHERE
                    service_id = $serviceID";

    $result = full_query($query);
    //$OnAppUserID = mysql_result( $result, 0 );
    $onAppUserIDArr = mysql_fetch_assoc($result);
    $onAppUserID = $onAppUserIDArr['onapp_user_id'];

    $module = new OnApp_UserModule($params);
    $onAppUser = $module->getOnAppObject('OnApp_User');
    $onAppUser->_id = $onAppUserID;

    $onAppUser->_time_zone = $module->getProductParam('SelectedTZs');
    $onAppUser->_locale = $module->getProductParam('SelectedLocales');

    $billingRow = $module->getBillingFieldName();
    $onAppUser->{$billingRow} = $module->getProductParam('SelectedPlans');

    $onAppUser->_user_group_id = $module->getProductParam('SelectedUserGroups');
    $onAppUser->save();

    if (!is_null($onAppUser->error)) {
        $errorMsg = $_LANG['onappuserserruserupgrade'] . ':<br/>';
        $errorMsg .= $onAppUser->getErrorsAsString('<br/>');

        return $errorMsg;
    }

    sendmessage($_LANG['onappusersupgradeaccount'], $serviceID);

    return 'success';
}

function onappusers_ClientAreaCustomButtonArray()
{
    global $_LANG;
    $buttons = array(
        $_LANG['onappusersgeneratenewpassword'] => 'GeneratePassword',
    );

    return $buttons;
}

function onappusers_GeneratePassword($params)
{
    global $_LANG;

    $serviceID = $params['serviceid'];
    $password = OnApp_UserModule::generatePassword();

    $query = "SELECT
                    onapp_user_id
                FROM
                    tblonappusers
                WHERE
                    service_id = $serviceID";

    $result = full_query($query);
    //$OnAppUserID = mysql_result( $result, 0 );
    $OnAppUserIDArr = mysql_fetch_assoc($result);
    $OnAppUserID = $OnAppUserIDArr['onapp_user_id'];

    $module = new OnApp_UserModule($params);
    $OnAppUser = $module->getOnAppObject('OnApp_User');
    $OnAppUser->_id = $OnAppUserID;
    $OnAppUser->_password = $password;
    $OnAppUser->save();

    if (!is_null($OnAppUser->error)) {
        $errorMsg = $_LANG['onappuserserruserupgrade'] . ':<br/>';
        $errorMsg .= $OnAppUser->getErrorsAsString('<br/>');

        return $errorMsg;
    }

    // Save OnApp login and password
    full_query(
        "UPDATE
                tblhosting
            SET
                password = '" . encrypt($password) . "'
            WHERE
                id = '$serviceID'"
    );

    sendmessage($_LANG['onappuserschangeaccountpassword'], $serviceID);

    return 'success';
}

function onappusers_ClientArea($params = '')
{
    if (isset($_GET['getstat'])) {
        onappusers_OutstandingDetails($params);
    }

    $html = OnApp_UserModule::injectServerRow($params);
    if ($GLOBALS['CONFIG']['Template'] == 'six') {
        $html .= file_get_contents(__DIR__ . '/includes/html/clientarea.6.html');
    } else {
        $html .= file_get_contents(__DIR__ . '/includes/html/clientarea.5.html');
    }

    OnApp_UserModule::parseLang($html);
    $html .= '<script type="text/javascript">'
        . 'var UID = ' . $params['clientsdetails']['userid'] . ';'
        . 'var PID = ' . $params['accountid'] . ';'
        . 'var LANG = ' . OnApp_UserModule::getJSLang() . ';</script>';

    return $html;
}

function onappusers_AdminLink($params)
{
    global $_LANG;
    $form = '<form target="_blank" action="http' . ($params['serversecure'] == 'on' ? 's' : '') . '://' . (empty($params['serverhostname']) ? $params['serverip'] : $params['serverhostname']) . '/users/sign_in" method="post">
                  <input type="hidden" name="user[login]" value="' . $params['serverusername'] . '" />
                  <input type="hidden" name="user[password]" value="' . $params['serverpassword'] . '" />
                  <input type="hidden" name="commit" value="Sign In" />
                  <input type="submit" value="' . $_LANG['onappuserslogintocp'] . '" class="btn btn-default" />
               </form>';

    return $form;
}

function onappusers_OutstandingDetails($params = '')
{
    $data = json_encode(OnApp_UserModule::getAmount($params));
    exit($data);
}

function onappusers_AdminServicesTabFields($params)
{
    $serviceID = $params['serviceid'];

    $query = "SELECT
                    onapp_user_id
                FROM
                    tblonappusers
                WHERE
                    service_id = $serviceID";

    $onAppUserID = '';
    $result = full_query($query);
    if ($result) {
        $onAppUserIDArr = mysql_fetch_assoc($result);
        $onAppUserID = $onAppUserIDArr['onapp_user_id'];
    }

    $fieldsarray = array(
        'OnApp User ID' => '<input type="text" name="onAppUserID" size="30" value="' . $onAppUserID . '" />',
    );

    return $fieldsarray;

}

function onappusers_AdminServicesTabFieldsSave($params)
{
    $clientsDetails = $params['clientsdetails'];
    $serverID = $params['serverid'];
    $serviceID = $params['serviceid'];
    $onAppUserIDNew = (int) $_POST['onAppUserID'];

    $query = "SELECT
                    onapp_user_id
                FROM
                    tblonappusers
                WHERE
                    server_id = $serverID AND
                    client_id = " . $clientsDetails['userid'] . " AND
                    service_id = $serviceID";

    $result = full_query($query);
    if (mysql_num_rows($result) === 0) {
        $module = new OnApp_UserModule($params);
        insert_query('tblonappusers', array(
            'server_id' => $serverID,
            'client_id' => $clientsDetails['userid'],
            'service_id' => $serviceID,
            'onapp_user_id' => $onAppUserIDNew,
            'billing_type' => $module->getBillingType(),
        ));
    } else {
        $sql = "UPDATE 
                      tblonappusers 
                  SET 
                      onapp_user_id = " . $onAppUserIDNew . " 
                  WHERE 
                    server_id = $serverID AND
                    client_id = " . $clientsDetails['userid'] . " AND
                    service_id = $serviceID";
        full_query($sql);
    }
}

/*function onappusers_AdminCustomButtonArray() {
    if(isset($_GET['userid']) && isset($_GET['id'])){
        OnApp_UserModule::checkIfServiceWasMoved($_GET['userid'], $_GET['id']);
    }
    return array();
}*/

