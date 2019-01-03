<?php

ini_set('memory_limit', '512M');
ini_set('max_execution_time', 0);
set_time_limit(0);

require_once(__DIR__ . '/../includes/php/OnApp_UserModule.php');

abstract class OnApp_UserModule_Cron
{
    protected $root;
    protected $clients;
    protected $fromDate;
    protected $tillDate;
    protected $fromDateUTC;
    protected $tillDateUTC;
    protected $timeZoneOffset;
    protected $currentDate;
    protected $cliOptions;
    protected $logEnabled = false;
    protected $printEnabled = false;
    protected $servers = array();
    protected $log = array();
    protected $whmcsuserid = -1;
    protected $autoapplycredit = false;
    protected $admin = '';

    public function __construct()
    {
        $this->checkCronkeyOrCLIMode();
        $this->root = __DIR__ . '/../../../../';

        $this->getRequiredFiles();
        $this->setCLIOptions();
        $this->checkSQL();
        $this->getServers();
        $this->getClients();

        $this->log(date('Y-m-d H:i:s'), 'Run at');

        $this->calculateDates();

        # set current date for invoice date and default invoice due date
        $this->currentDate = date('Ymd');

        $this->run();
    }

    private function checkCronkeyOrCLIMode()
    {
        $keyFile = __DIR__ . '/../cronkey.php';
        if (file_exists($keyFile)) {
            include_once $keyFile;
            if (isset($cronkey)) {
                $cronkey = trim($cronkey);
                if ($cronkey !== '') {
                    if (isset($_POST['key'])) {
                        $key = trim($_POST['key']);
                        if ($key === $cronkey) {
                            return true;
                        }
                    }
                }
            }
        }

        if (PHP_SAPI != 'cli') {
            if (!empty($_SERVER['REMOTE_ADDR'])) {
                $this->log['error'] = 'Not allowed!';
                exit('Not allowed!' . PHP_EOL);
            }
        }
    }

    protected function getRequiredFiles()
    {
        global $whmcsmysql, $cc_encryption_hash, $templates_compiledir, $CONFIG, $_LANG, $whmcs;

        if (file_exists($this->root . 'init.php')) {
            require_once $this->root . 'init.php';
        } else {
            require_once $this->root . 'dbconnect.php';
            include_once $this->root . 'includes/functions.php';
        }

        require_once __DIR__ . '/../includes/php/CURL.php';
        require_once $this->root . '/modules/servers/onappusers/includes/php/SOP.php';
        include_once $this->root . 'includes/processinvoices.php';
        include_once $this->root . 'includes/invoicefunctions.php';

        error_reporting(E_ALL ^ E_NOTICE);
        ini_set('display_errors', 'On');
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 0);
        set_time_limit(0);
    }

    private function setCLIOptions()
    {
        $options = array(
            'since' => array(
                'description' => 'date to start(2016-08-15 00:00)',
                'validation' => '^(20\d{2})-([0][1-9]|[1][0-2])-([\d]{2}) ([0-1][0-9]|[2][0-3]):([0-5][0-9])$',
                'short' => 's',
            ),
            'till' => array(
                'description' => 'date to finish(2016-08-16 00:00)',
                'validation' => '^(20\d{2})-([0][1-9]|[1][0-2])-([\d]{2}) ([0-1][0-9]|[2][0-3]):([0-5][0-9])$',
                'short' => 't',
            ),
            'log' => array(
                'description' => 'log data to file',
                'short' => 'l',
            ),
            'print' => array(
                'description' => 'print data to screen',
                'short' => 'p',
            ),
            'whmcsuserid' => array(
                'description' => 'generate invoice for a certain whmcs user',
                'validation' => '^(\d{1,})$',
                'short' => 'u',
            ),
            'autoapplycredit' => array(
                'description' => 'auto apply credit',
                'short' => 'c',
            ),
        );

        $options = new SOP($options);
        $options->setBanner('OnApp User Module detailed statistics and invoices processor');
        $this->cliOptions = $options->parse();

        if (isset($_POST['since'])) {
            $this->cliOptions->since = $_POST['since'];
        }
        if (isset($_POST['till'])) {
            $this->cliOptions->till = $_POST['till'];
        }
        if (isset($_POST['log'])) {
            $this->cliOptions->log = true;
        }
        if (isset($_POST['print'])) {
            $this->cliOptions->print = true;
        }
        if (isset($_POST['whmcsuserid'])) {
            $this->cliOptions->whmcsuserid = $_POST['whmcsuserid'];
        }
        if (isset($_POST['autoapplycredit'])) {
            $this->cliOptions->autoapplycredit = true;
        }

        if (isset($this->cliOptions->log)) {
            $this->logEnabled = true;
        }
        if (isset($this->cliOptions->print)) {
            $this->printEnabled = true;
        }
        if (isset($this->cliOptions->whmcsuserid)) {
            $this->whmcsuserid = $this->cliOptions->whmcsuserid;
        }
        if (isset($this->cliOptions->autoapplycredit)) {
            $this->autoapplycredit = true;
        }
    }

    private function checkSQL()
    {
        if (file_exists($file = __DIR__ . '/../module.sql')) {
            logactivity('OnApp User Module: process SQL file, called from cronjob.');

            $sql = file_get_contents($file);
            $sql = explode(PHP_EOL . PHP_EOL, $sql);

            foreach ($sql as $qry) {
                full_query($qry);
            }
            unlink($file);
        }
    }

    protected function getServers()
    {
        $sql = 'SELECT
                    id,
                    secure,
                    username,
                    hostname,
                    `password`,
                    ipaddress
                FROM
                    tblservers
                WHERE
                    type = "onappusers"';
        $result = full_query($sql);
        while ($server = mysql_fetch_assoc($result)) {
            $server['password'] = decrypt($server['password']);
            if ($server['secure']) {
                $server['address'] = 'https://';
            } else {
                $server['address'] = 'http://';
            }
            if (empty($server['ipaddress'])) {
                $server['address'] .= $server['hostname'];
            } else {
                $server['address'] .= $server['ipaddress'];
            }
            unset($server['ipaddress'], $server['hostname'], $server['secure']);
            $this->servers[$server['id']] = $server;
        }
    }

    protected function getClients()
    {
        $certainWHMCSUser = ($this->whmcsuserid != -1) ? 'AND tblclients.id = ' . $this->whmcsuserid : '';
        $sql = 'SELECT
                    tblclients.taxexempt,
                    tblclients.state,
                    tblclients.country,
                    tblclients.currency,
                    tblclients.language,
                    tblclients.credit,
                    tblcurrencies.rate,
                    tblhosting.paymentmethod,
                    tblhosting.domain,
                    tblhosting.id AS service_id,
                    tblhosting.domainstatus,
                    tblonappusers.server_id,
                    tblonappusers.client_id,
                    tblonappusers.onapp_user_id,
                    tblonappusers.billing_type,
                    tblproducts.tax,
                    tblproducts.name AS packagename,
                    tblproducts.configoption1                    
                FROM
                    tblonappusers
                LEFT JOIN tblhosting ON
                    tblhosting.userid = tblonappusers.client_id
                    AND tblhosting.server = tblonappusers.server_id
                    AND tblhosting.id = tblonappusers.service_id
                LEFT JOIN tblproducts ON
                    tblhosting.packageid = tblproducts.id
                    AND tblproducts.servertype = "onappusers"
                LEFT JOIN tblclients ON
                    tblclients.id = tblonappusers.client_id
                LEFT JOIN tblcurrencies ON
                    tblcurrencies.id = tblclients.currency
                WHERE
                    tblhosting.domainstatus IN ( "Active", "Suspended" )
                    AND tblproducts.name IS NOT NULL
                    ' . $certainWHMCSUser . '
                    ORDER BY tblonappusers.`onapp_user_id`';

        $this->clients = full_query($sql);
    }

    protected function log($data, $key = null)
    {
        $data = ($key ? $key . ': ' : '') . $data;
        if ($this->printEnabled) {
            echo $data . PHP_EOL;
        }

        if (!$this->logEnabled) {
            return;
        }

        $this->log[] = $data;
    }

    private function calculateDates()
    {
        $tmp = time();
        $this->timeZoneOffset = strtotime(date('Y-m-d H:i:s', $tmp)) - strtotime(gmdate('Y-m-d H:i:s', $tmp));

        if (isset($this->cliOptions->since)) {
            $fromDate = $this->cliOptions->since;
        } else {
            $fromDate = date('Y-m-d 00:00', strtotime('first day of last month'));
        }
        if (isset($this->cliOptions->till)) {
            $tillDate = $this->cliOptions->till;
        } else {
            $tillDate = date('Y-m-d 00:00', strtotime('first day of next month', strtotime($fromDate)));
        }

        $this->fromDateUTC = $this->getUTCTime($fromDate, 'Y-m-d H:30');
        $this->tillDateUTC = $this->getUTCTime($tillDate, 'Y-m-d H:30');

        $this->fromDate = $fromDate;
        $this->tillDate = $tillDate;
    }

    protected function getUTCTime($date, $format = 'Y-m-d H:i')
    {
        return gmdate($format, strtotime($date));
    }

    abstract protected function run();

    public function __destruct()
    {
        if ($this->logEnabled) {
            $this->writeLog();
        }
    }

    private function writeLog()
    {
        $c = get_called_class();
        $logDir = __DIR__ . '/logs/' . $c::TYPE . '/';

        if (!is_dir($logDir) && !mkdir($logDir) && !is_dir($logDir)) {
            echo 'Can\'t create ' . $logDir . ' exists and is writable!' . PHP_EOL;

            return;
        }

        $logFile = $logDir . date('Y-m-d-H-i-s');

        if (!(file_exists($logFile) && is_writable($logFile))) {
            if (!$log = @fopen($logFile, 'w')) {
                echo 'Can\'t write log file ' . $logFile . PHP_EOL;
            } else {
                fclose($log);
            }
        }

        $log = print_r($this->log, true);

        file_put_contents($logFile, $log);
        echo PHP_EOL, ' Log has been saved in ' . $logFile, PHP_EOL;
    }

    protected function getAmount(array $user, $fromDate = null, $tillDate = null)
    {
        if (!$fromDate) {
            $fromDate = $this->fromDateUTC;
        }
        if (!$tillDate) {
            $tillDate = $this->tillDateUTC;
        }

        $date = array(
            'period[startdate]' => $fromDate,
            'period[enddate]' => $tillDate,
        );
        $data = $this->getResourcesData($user, $date);

        if (!$data) {
            return false;
        }

        $data = $data->user_stat;
        $unset = array(
            'vm_stats',
            'stat_time',
            'user_resources_cost',
            'currency_code',
            'user_id',
            'template_cost',
        );
        $this->dataTMP = clone $data;
        foreach ($data as $key => &$value) {
            if (in_array($key, $unset)) {
                unset($data->$key);
            } else {
                $data->$key *= $user['rate'];
            }
        }

        return $data;
    }

    protected function getResourcesData($client, $date)
    {
        $date = http_build_query($date);
        $url = $this->servers[$client['server_id']]['address'] . '/users/' . $client['onapp_user_id'] . '/user_statistics.json?' . $date;

        $data = $this->sendRequest(
            $url,
            $this->servers[$client['server_id']]['username'],
            $this->servers[$client['server_id']]['password']
        );

        if ($data) {
            return json_decode($data);
        } else {
            return false;
        }
    }

    protected function getAuthedOnAppObj($client)
    {
        return new OnApp_Factory(
            $this->servers[$client['server_id']]['address'],
            $this->servers[$client['server_id']]['username'],
            $this->servers[$client['server_id']]['password']
        );
    }

    protected function sendRequest($url, $user, $password)
    {
        $curl = new CURL();
        $curl->addOption(CURLOPT_USERPWD, $user . ':' . $password);
        $curl->addOption(CURLOPT_HTTPHEADER, array('Accept: application/json', 'Content-type: application/json'));
        $curl->addOption(CURLOPT_HEADER, true);
        $data = $curl->get($url);

        if ($curl->getRequestInfo('http_code') != 200) {
            $this->log($url, 'API request');
            $this->log($curl->getRequestInfo('response_body'), '[ERROR] request response');

            return false;
        } else {
            return $data;
        }
    }

    protected function generateInvoiceData($data, array $client)
    {
        global $_LANG;

        OnApp_UserModule::loadLang($client['language']);

        //check if invoice should be generated
        $fromTime = strtotime($this->fromDate);
        $tillTime = strtotime($this->tillDate);
        if ($fromTime >= $tillTime) {
            return false;
        }

        if (OnApp_UserModule::getConfigOptionByClientData($client, 'DueDateCurrent') == 1) {
            $dueDate = $this->currentDate;
        } else {
            $dueDate = date('Ymd', (time() + $GLOBALS['CONFIG']['CreateInvoiceDaysBefore'] * 86400));
        }

        //check if the item should be taxed
        $taxed = empty($client['taxexempt']) && (int) $client['tax'];
        if ($taxed) {
            $taxrate = getTaxRate(1, $client['state'], $client['country']);
            $taxrate = $taxrate['rate'];
        } else {
            $taxrate = '';
        }

        $timeZone = ' UTC' . ($this->timeZoneOffset >= 0 ? '+' : '-') . ($this->timeZoneOffset / 3600);

        $this->fromDate = date($_LANG['onappusersstatdateformat'], $fromTime);
        $this->tillDate = date($_LANG['onappusersstatdateformat'], $tillTime);
        $invoiceDescription = array(
            $_LANG['onappusersstatproduct'] . $client['packagename'],
            $_LANG['onappusersstatperiod'] . $this->fromDate . ' - ' . $this->tillDate . $timeZone,
        );
        $invoiceDescription = implode(PHP_EOL, $invoiceDescription);

        $return = array(
            'userid' => $client['client_id'],
            'date' => $this->currentDate,
            'duedate' => $dueDate,
            'paymentmethod' => $client['paymentmethod'],
            'taxrate' => $taxrate,
            'sendinvoice' => true,
            'itemdescription1' => $invoiceDescription,
            'itemamount1' => 0,
            'itemtaxed1' => $taxed,
            'status' => 'Unpaid',
        );
        if ($this->autoapplycredit) {
            $return['autoapplycredit'] = true;
        }

        if (property_exists($data, 'total_cost_with_discount')) {
            $return = array_merge($return, array(
                'itemdescription2' => $_LANG['onappusers_invoice_total_cost'],
                'itemamount2' => $data->total_cost_with_discount,
                'itemtaxed2' => $taxed,
            ));
        } else {
            unset($data->total_cost);

            $i = 1;
            foreach ($data as $key => $value) {
                if ($value <= 0) {
                    continue;
                }
                $langIndex = 'onappusers_invoice_' . $key;
                if (!isset($_LANG[$langIndex])) {
                    continue;
                }
                $tmp = array(
                    'itemdescription' . ++$i => $_LANG[$langIndex],
                    'itemamount' . $i => $value,
                    'itemtaxed' . $i => $taxed,
                );
                $return = array_merge($return, $tmp);
            }
        }

        $this->log(print_r($return, true), 'Invoice data');

        return $return;
    }

    protected function getAdminUsername()
    {
        if ($this->admin) {
            return $this->admin;
        }

        $qry = 'SELECT
                    `username`
                FROM
                    `tbladmins`
                WHERE
                    disabled = 0
                LIMIT 1';
        $res = mysql_query($qry);
        $adminArr = mysql_fetch_assoc($res);
        $this->admin = $adminArr['username'];

        return $this->admin;
    }

    protected function getTotalCost($obj)
    {
        if (!property_exists($obj, 'total_cost')) {
            return 0;
        }

        return property_exists($obj, 'total_cost_with_discount') ?
            $obj->total_cost_with_discount : $obj->total_cost;
    }
}
