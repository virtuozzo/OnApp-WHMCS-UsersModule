<?php

if (!defined('ONAPP_WRAPPER_INIT')) {
    $wrapperPath = __DIR__ . '/../../../../../includes/wrapper/OnAppInit.php';
    if (file_exists($wrapperPath)) {
        require_once($wrapperPath);
        define('ONAPP_WRAPPER_INIT', $wrapperPath);
    }
}

class OnApp_UserModule
{
    const MODULE_NAME = 'onappusers';

    const LAST_CP_VERSION_WITHOUT_BUCKETS = 5.5;
    const LAST_CP_VERSION_WITHOUT_BILLING_USER = 5.1;

    protected $params = array();
    private $server;

    private $productParams = array();

    private $billingTypes = array(
        0 => 'postpaid',
        1 => 'prepaid',
    );

    private $defaultBillingTypeID = 0;

    private $apiVersionData = null;
    private $wrapperVersion = null;

    private $lastErrorMessage = '';

    public function __construct($params)
    {
        $this->server = new stdClass;

        if (is_array($params)) {
            $this->params = $params;
        }

        if ($params['serversecure'] == 'on') {
            $this->server->ip = 'https://';
        } else {
            $this->server->ip = 'http://';
        }
        $this->server->ip .= empty($params['serverip']) ? $params['serverhostname'] : $params['serverip'];
        $this->server->user = $params['serverusername'];
        $this->server->pass = $params['serverpassword'];

        if (isset($this->params['configoption1'])) {
            $paramsConfigoption1 = html_entity_decode($this->params['configoption1']);
            $this->productParams = json_decode($paramsConfigoption1, true);
        }

        if (!isset($this->params['serverid'])) {
            $this->params['serverid'] = 0;
        }

        $this->reinitLANG();
    }

    private function reinitLANG()
    {
        global $_LANG;

        if ($this->getAPIVersion() <= self::LAST_CP_VERSION_WITHOUT_BUCKETS) {
            return;
        }

        $_LANG['onappusersbindingplanstitle'] = $_LANG['onappusersbucketstitle'];
        $_LANG['onappusersbindingplanssuspendedtitle'] = $_LANG['onappusersbucketssuspendedtitle'];
        $_LANG['onappusersusecustombillingplans'] = $_LANG['onappusersusecustombuckets'];
        $_LANG['onappusersprefixforcustombillingplans'] = $_LANG['onappusersprefixforcustombuckets'];
        $_LANG['onappuserscopybillingplanerror'] = $_LANG['onappuserscopybucketerror'];
    }

    private function getAPIVersion()
    {
        $apiVersionData = $this->getAPIVersionData();

        return $apiVersionData['version'];
    }

    private function getAPIVersionData()
    {
        if (!$this->apiVersionData) {
            $obj = new OnApp_Factory($this->server->ip, $this->server->user, $this->server->pass);
            $this->apiVersionData = array(
                'version' => (float) trim($obj->getAPIVersion()),
                'message' => trim($obj->getErrorsAsString(', ')),
            );
        }

        return $this->apiVersionData;
    }

    public static function getAmount(array $params)
    {
        if ($_GET['tz_offset'] != 0) {
            $dateFrom = date('Y-m-d H:i', strtotime($_GET['start']) + ($_GET['tz_offset'] * 60));
            $dateTill = date('Y-m-d H:i', strtotime($_GET['end']) + ($_GET['tz_offset'] * 60));
        } else {
            $dateFrom = $_GET['start'];
            $dateTill = $_GET['end'];
        }
        $date = array(
            'period[startdate]' => $dateFrom,
            'period[enddate]' => $dateTill,
        );

        $data = self::getResourcesData($params, $date);

        if (!$data) {
            return false;
        }

        $sql = 'SELECT
                    `code`,
                    `rate`
                FROM
                  `tblcurrencies`
                WHERE
                  `id` = ' . $params['clientsdetails']['currency'];
        $rate = mysql_fetch_assoc(full_query($sql));

        $data = $data->user_stat;
        $unset = array(
            'vm_stats',
            'stat_time',
            'user_id',
        );
        foreach ($data as $key => &$value) {
            if (in_array($key, $unset)) {
                unset($data->$key);
            } else {
                $data->$key *= $rate['rate'];
            }
        }
        $data->currency_code = $rate['code'];

        return $data;
    }

    private static function getResourcesData($params, $date)
    {
        $sql = 'SELECT
                    `server_id`,
                    `client_id` AS whmcs_user_id,
                    `onapp_user_id`
                FROM
                  `tblonappusers`
                WHERE
                    `service_id` = ' . $params['serviceid'] . '
                    LIMIT 1';
        $user = mysql_fetch_assoc(full_query($sql));

        if ($params['serversecure'] == 'on') {
            $serverAddr = 'https://';
        } else {
            $serverAddr = 'http://';
        }
        $serverAddr .= empty($params['serverhostname']) ? $params['serverip'] : $params['serverhostname'];

        $date = http_build_query($date);

        $url = $serverAddr . '/users/' . $user['onapp_user_id'] . '/user_statistics.json?' . $date;
        $data = self::sendRequest($url, $params['serverusername'], $params['serverpassword']);

        if ($data) {
            return json_decode($data);
        } else {
            return false;
        }
    }

    private static function sendRequest($url, $user, $password)
    {
        require_once __DIR__ . '/../../includes/php/CURL.php';

        $curl = new CURL();
        $curl->addOption(CURLOPT_USERPWD, $user . ':' . $password);
        $curl->addOption(CURLOPT_HTTPHEADER, array('Accept: application/json', 'Content-type: application/json'));
        $curl->addOption(CURLOPT_HEADER, true);
        $data = $curl->get($url);

        if ($curl->getRequestInfo('http_code') != 200) {
            return false;
        } else {
            return $data;
        }
    }

    public static function generatePassword()
    {
        return substr(str_shuffle('~!@$%^&*(){}|0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0,
            20);
    }

    public static function checkIfServiceWasMoved($userID, $serviceID)
    {
        $serviceID = (int) $serviceID;
        $userID = (int) $userID;

        if ($serviceID <= 0 || $userID <= 0) {
            return false;
        }

        $query = "SELECT * FROM tblonappusers WHERE service_id = " . $serviceID;

        $result = full_query($query);
        if (!$result) {
            return false;
        }
        if (mysql_num_rows($result) !== 1) {
            return false;
        }

        $onappuser = mysql_fetch_assoc($result);
        if (!$onappuser) {
            return false;
        }

        if ($userID !== (int) $onappuser['client_id']) {
            $sql = "UPDATE tblonappusers SET client_id = " . $userID . " WHERE service_id = " . $serviceID;
            full_query($sql);

            return true;
        }

        return false;
    }

    public static function loadLang($lang = null)
    {
        global $_LANG, $CONFIG;

        if (!isset($_LANG) || !is_array($_LANG)) {
            $_LANG = array();
        }

        $languagesDir = __DIR__ . '/../../lang/';

        $currentDir = getcwd();
        chdir($languagesDir);
        $availableLangs = glob('*.*');

        if (empty($lang)) {
            $language = isset($_SESSION['Language']) ? $_SESSION['Language'] : $CONFIG['Language'];
        } else {
            $language = $lang;
        }
        $language = strtolower($language);

        $selectedLanguageFile = 'English.txt';
        foreach ($availableLangs as $availableLang) {
            $availableLangLowerCase = strtolower($availableLang);
            if (!preg_match('/' . preg_quote($language, '/') . '\./', $availableLangLowerCase)) {
                continue;
            }
            $selectedLanguageFile = $availableLang;
        }

        $tempLang = file_get_contents($languagesDir . $selectedLanguageFile);
        eval ($tempLang);
        chdir($currentDir);
    }

    public static function getJSLang()
    {
        global $_LANG;

        $result = array();
        foreach ($_LANG as $key => $value) {
            //remove invalid characters
            $result[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        return json_encode($result);
    }

    public static function parseLang(&$html)
    {
        $html = preg_replace_callback(
            '#{\$LANG.(.*)}#U',
            function ($matches) {
                global $_LANG;

                return $_LANG[ $matches[ 1 ] ];
            },
            $html
        );

        return $html;
    }

    public static function injectServerRow($params)
    {
        $key = md5(uniqid(rand(1, 999999), true));

        $server = empty($params['serverhostname']) ? $params['serverip'] : $params['serverhostname'];
        if (strpos($server, 'http') === false) {
            $scheme = $params['serversecure'] ? 'https://' : 'http://';
            $server = $scheme . $server;
        }

        $data = array(
            'login' => $params['username'],
            'password' => $params['password'],
            'server' => $server,
        );
        $data = json_encode($data) . '%%%';
        $crypttext = self::doEncrypt($data, $key);
        $_SESSION['utk'] = array(
            $key . md5(uniqid(rand(1, 999999), true)),
            base64_encode(base64_encode($crypttext))
        );

        $html = file_get_contents(__DIR__ . '/../../includes/html/serverData.html');
        $html = str_replace('{###}', md5(uniqid(rand(1, 999999), true)), $html);
        $html .= '<script type="text/javascript">'
            . 'var SERVER = "' . $server . '";'
            . 'var injTarget = "' . $params['username'] . ' / ' . $params['password'] . '";'
            . '</script>';

        return $html;
    }

    public static function doEncrypt($data, $key)
    {
        if (function_exists('mcrypt_get_iv_size')) {
            return self::doEncryptByMcrypt($data, $key);
        }

        return self::doEncryptByOpenSSL($data, $key);
    }

    public static function doEncryptByMcrypt($data, $key)
    {
        $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND);

        return mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $data, MCRYPT_MODE_ECB, $iv);
    }

    public static function doEncryptByOpenSSL($data, $key)
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));

        return $iv . openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
    }

    public static function getConfigOptionByClientData($client, $paramName)
    {
        if (!isset($client['configoption1'])) {
            return '';
        }

        $configOption = html_entity_decode($client['configoption1']);
        $configOption = json_decode($configOption, true);

        if (!isset($configOption[$paramName])) {
            return '';
        }

        if (!isset($configOption[$paramName][$client['server_id']])) {
            return '';
        }

        return $configOption[$paramName][$client['server_id']];
    }

    public function getBillingType()
    {
        $billingTypeID = null;
        if (isset($this->productParams['SelectedBillingType'][$this->params['serverid']])) {
            $billingTypeID = $this->productParams['SelectedBillingType'][$this->params['serverid']];
        }

        if ($billingTypeID === null || !isset($this->billingTypes[$billingTypeID])) {
            return $this->billingTypes[$this->defaultBillingTypeID];
        }

        return $this->billingTypes[$billingTypeID];
    }

    public function getCustomBillingPlanIDIfItsNotDefault($currentBillingPlanID)
    {
        return $currentBillingPlanID !== $this->getDefaultBillingPlanID() ? $currentBillingPlanID : 0;
    }

    public function getDefaultBillingPlanID()
    {
        return $this->getProductParam('SelectedPlans');
    }

    public function getDefaultSuspendBillingPlanID()
    {
        return $this->getProductParam('SelectedSuspendedPlans');
    }

    public function deleteCustomBillingPlan($billingPlanID)
    {
        if (!$billingPlanID) {
            return;
        }

        if ($this->getDefaultBillingPlanID() == $billingPlanID) {
            return;
        }

        if ($this->getDefaultSuspendBillingPlanID() == $billingPlanID) {
            return;
        }

        $billingPlan = $this->getBillingPlanObj();

        $billingPlan->_id = $billingPlanID;
        $billingPlan->delete();
    }

    public function getProductParam($paramName, $serverID = 0)
    {
        if (!$serverID) {
            $serverID = $this->params['serverid'];
        }

        if (!isset($this->productParams[$paramName])) {
            return '';
        }

        if (!isset($this->productParams[$paramName][$serverID])) {
            return '';
        }

        return $this->productParams[$paramName][$serverID];
    }

    public function getUserGroups()
    {
        $data = $this->getOnAppObject('OnApp_UserGroup')->getList();

        return $this->buildArray($data);
    }

    public function getOnAppObject($class)
    {
        $obj = new $class;
        $obj->auth($this->server->ip, $this->server->user, $this->server->pass);

        return $obj;
    }

    private function buildArray($data)
    {
        $tmp = array();
        foreach ($data as $item) {
            $tmp[$item->_id] = $item->_label;
        }

        return $tmp;
    }

    public function getRoles()
    {
        $data = $this->getOnAppObject('OnApp_Role')->getList();

        return $this->buildArray($data);
    }

    public function getBillingPlans()
    {
        return $this->buildArray($this->getBillingPlanObj()->getList());
    }

    private function getBillingPlanObj()
    {
        $apiVersion = $this->getAPIVersion();
        if ($apiVersion > self::LAST_CP_VERSION_WITHOUT_BUCKETS) {
            $obj = $this->getOnAppObject('OnApp_BillingBucket');
        } elseif ($apiVersion > self::LAST_CP_VERSION_WITHOUT_BILLING_USER) {
            $obj = $this->getOnAppObject('OnApp_BillingUser');
        } else {
            $obj = $this->getOnAppObject('OnApp_BillingPlan');
        }

        return $obj;
    }

    public function getBillingFieldName()
    {
        if ($this->getAPIVersion() > self::LAST_CP_VERSION_WITHOUT_BUCKETS) {
            return '_bucket_id';
        }

        return '_billing_plan_id';
    }

    public function getBillingPlanID()
    {
        global $_LANG;

        $defaultBillingPlanID = $this->getDefaultBillingPlanID();
        if (!$this->isCustomBillingPlans()) {
            return $defaultBillingPlanID;
        }

        $billingPlan = $this->getBillingPlanObj();

        if ($this->getAPIVersion() > self::LAST_CP_VERSION_WITHOUT_BUCKETS) {
            $billingPlan->bucketClone($defaultBillingPlanID);

            $errorMessage = $this->getErrorMessageOfWrapperResult($billingPlan);

            if ($errorMessage) {
                $this->setLastErrorMessage($_LANG['onappuserscopybillingplanerror'] . ': ' . $errorMessage);

                return 0;
            }

            $newBillingPlanID = $billingPlan->_obj->_id;
        } else {
            $billingPlan->_id = $defaultBillingPlanID;
            $newBillingPlan = $billingPlan->create_copy();

            $newBillingPlanID = $newBillingPlan->_id;
        }

        return $newBillingPlanID;
    }

    public function isCustomBillingPlans()
    {
        $useCustomBillingPlans = false;
        if (isset($this->productParams['CustomBillingPlansCurrent'][$this->params['serverid']])) {
            $useCustomBillingPlans = $this->productParams['CustomBillingPlansCurrent'][$this->params['serverid']];
        }

        return $useCustomBillingPlans;
    }

    public function getErrorMessageOfWrapperResult($onAppObj)
    {
        if (!is_null($onAppObj->getErrorsAsArray())) {
            return $onAppObj->getErrorsAsString(', ');
        }

        if (!is_null($onAppObj->_obj->getErrorsAsArray())) {
            return $onAppObj->_obj->getErrorsAsString(', ');
        }

        if (is_null($onAppObj->_obj->_id)) {
            return '_obj->_id is null';
        }

        return '';
    }

    public function renameBillingPlanIfCustom($billingPlanID, $onAppUserID, $billingPlanPrefix = '')
    {
        if (!$this->isCustomBillingPlans()) {
            return;
        }

        $billingPlanPrefixParts = explode('@', $billingPlanPrefix);
        $billingPlanPrefix = $billingPlanPrefixParts[0];

        $this->setNewNameOfBillingPlan(
            $billingPlanID,
            $this->getPrefixForCustomBillingPlans() .
            ($billingPlanPrefix !== '' ? '-' . $billingPlanPrefix . '-' : '') .
            $this->getBillingPlanName($this->getDefaultBillingPlanID()) .
            '-' .
            $onAppUserID
        );
    }

    private function setNewNameOfBillingPlan($id, $newName)
    {
        $billingPlanObj = $this->getBillingPlanObj();
        $billingPlanObj->_id = $id;
        $billingPlanObj->_label = $newName;
        $billingPlanObj->save();
    }

    public function getPrefixForCustomBillingPlans()
    {
        $prefixForCustomBillingPlans = '';
        if (isset($this->productParams['PrefixForCustomBillingPlansCurrent'][$this->params['serverid']])) {
            $prefixForCustomBillingPlans = $this->productParams['PrefixForCustomBillingPlansCurrent'][$this->params['serverid']];
        }

        return $prefixForCustomBillingPlans;
    }

    public function getBillingPlanName($id)
    {
        $billingPlanObj = $this->getBillingPlanObj();
        $billingPlanObj->_id = $id;
        $billingPlanObj->load();

        return $billingPlanObj->_obj->_label;
    }

    public function getLastErrorMessage()
    {
        return $this->lastErrorMessage;
    }

    public function setLastErrorMessage($lastErrorMessage)
    {
        $this->lastErrorMessage = $lastErrorMessage;
    }

    public function getLocales()
    {
        $tmp = array();
        foreach ($this->getOnAppObject('OnApp_Locale')->getList() as $locale) {
            if (empty($locale->name)) {
                continue;
            }
            $index = $locale->code ? $locale->code : $locale->name;
            $tmp[$index] = $locale->name;
        }
        if (!isset($tmp['en'])) {
            $tmp['en'] = 'en';
        }

        return $tmp;
    }

    public function checkWrapperVersion()
    {
        $result = array();
        $result['apiMessage'] = $this->getAPIVersionMessage();
        $result['wrapperVersion'] = $this->getWrapperVersion();
        $result['apiVersion'] = $this->getAPIVersion();
        if (($result['wrapperVersion'] == '') || ($result['apiVersion'] == '')) {
            $result['status'] = false;

            return $result;
        }

        $wrapperVersionAr = preg_split('/[.,]/', $result['wrapperVersion'], null, PREG_SPLIT_NO_EMPTY);
        if ((count($wrapperVersionAr) == 1) && ($wrapperVersionAr[0] == '')) {
            $result['status'] = false;

            return $result;
        }

        $apiVersionAr = preg_split('/[.,]/', $result['apiVersion'], null, PREG_SPLIT_NO_EMPTY);
        if ((count($apiVersionAr) == 1) && ($apiVersionAr[0] == '')) {
            $result['status'] = false;

            return $result;
        }

        $result['status'] = true;
        foreach ($apiVersionAr as $apiVersionKey => $apiVersionValue) {
            if (!isset($wrapperVersionAr[$apiVersionKey])) {
                $result['status'] = false;
                break;
            }

            $apiVersionValue = (int) $apiVersionValue;
            $wrapperVersionValue = (int) $wrapperVersionAr[$apiVersionKey];

            if ($apiVersionValue == $wrapperVersionValue) {
                continue;
            }

            $result['status'] = ($wrapperVersionAr[$apiVersionKey] > $apiVersionValue);
            break;
        }

        return $result;
    }

    private function getAPIVersionMessage()
    {
        $apiVersionData = $this->getAPIVersionData();

        return $apiVersionData['message'];
    }

    private function getWrapperVersion()
    {
        if (!$this->wrapperVersion) {
            $pathToWrapper = realpath(ROOTDIR) . '/includes/wrapper/';
            $this->wrapperVersion = (float) file_get_contents($pathToWrapper . 'version.txt');
        }

        return $this->wrapperVersion;
    }

    public function getExtData($name)
    {
        if (!isset($this->params['serverid'])) {
            return false;
        }
        $results = array();
        $sql = "SELECT * FROM tblservers WHERE id = " . $this->params['serverid'];

        $result = mysql_fetch_assoc(full_query($sql));
        if (!$result) {
            return false;
        }

        // check we have some vars
        if ($result) {
            $data = htmlspecialchars_decode($result['accesshash']);
            $results = $this->sortReturn($data);
        }

        if (!is_array($results)) {
            return array();
        }

        if (isset($results[$name])) {
            return $results[$name];
        }

        return false;
    }

    public function sortReturn($data)
    {
        preg_match_all('/<(.*?)>([^<]+)<\/\\1>/i', $data, $matches);
        $result = array();
        foreach ($matches[1] as $k => $v) {
            $result[$v] = $matches[2][$k];
        }

        return $result;
    }

    public function getModuleVersion()
    {
        return file_get_contents(__DIR__ . '/../../version');
    }
}
