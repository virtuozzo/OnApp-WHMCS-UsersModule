<?php

require __DIR__ . '/common.php';

class OnApp_UserModule_Cron_Hourly extends OnApp_UserModule_Cron
{
    const TYPE = 'hourly';
    const INVOICE_NUMBER_PREFIX = 'whmcs-pre-pay-payment';
    const LOW_CREDITS_BALANCE_LEVEL_DAYS = 7;

    protected function run()
    {
        $this->getStat();
    }

    private function getStat()
    {
        global $_LANG;

        $finish = strtotime(gmdate('Y-m-d H:i:00')) + 24 * 3600;

        while ($client = mysql_fetch_assoc($this->clients)) {
            if ($client['billing_type'] !== 'prepaid') {
                continue;
            }

            $paramsConfigoption1 = html_entity_decode($client['configoption1']);
            $productParams = json_decode($paramsConfigoption1, true);
            if (isset($productParams['UnsuspendOnPositiveBalance'][$client['server_id']])) {
                $unsuspendOnPositiveBalance = $productParams['UnsuspendOnPositiveBalance'][$client['server_id']];
            }

            if ($unsuspendOnPositiveBalance && $client['domainstatus'] === 'Suspended') {
                $credit = (float) $client['credit'];
                if ($credit > 0) {
                    if ($this->unsuspendUser($client)) {
                        $client['domainstatus'] = 'Active';
                    }
                }
            }

            if ($client['domainstatus'] !== 'Active') {
                continue;
            }

            OnApp_UserModule::loadLang($client['language']);

            $this->log('WHMCS user ID: ' . $client['client_id']);
            $this->log('OnApp user ID: ' . $client['onapp_user_id']);
            $this->log('Server ID: ' . $client['server_id']);

            $initStartDate = $this->getLastStatRetrievingDate($client);

            $this->log($initStartDate, 'Start date');

            $start = strtotime($initStartDate);
            $endDate = null;
            $startDateForOutput = null;
            //$hasData = false;
            $lastNewBalance = 0;
            $lastNewBalanceDate = '';
            $lastNewBalanceCurrencyID = '';
            while ($start + 3600 < $finish) {
                $startDate = date('Y-m-d H:10:s', $start);
                $endDate = date('Y-m-d H:10:s', $start + 3600);

                $start += 3600;

                $clientAmount = $this->getAmount($client, $startDate, $endDate);
                if (!$clientAmount) {
                    continue;
                }
                if (!$clientAmount->total_cost) {
                    continue;
                }

                $this->log($startDate . ' - ' . $endDate, 'Period');
                $this->log($clientAmount->total_cost, 'Cost');

                //$hasData = true;

                $chargeClientData = $this->chargeClient($client, $clientAmount->total_cost, $startDate, $endDate);

                if (!$chargeClientData['status']) {
                    break;
                }

                $lastNewBalance = $chargeClientData['new_balance'];
                $lastNewBalanceCurrencyID = $client['currency'];
                $lastNewBalanceDate = $endDate;

                $this->saveHourlyStat($client, $clientAmount->total_cost, $startDate, $endDate);
                $this->saveLastCheckDate($client, $endDate);

                if (!$startDateForOutput) {
                    $startDateForOutput = $startDate;
                }

                if ($lastNewBalance < 0) {
                    break;
                }
            }

            /*            if (!$hasData) {
                            $this->saveLastCheckDate($client, date('Y-m-d H:10:s', $start - 2 * 24 * 3600));
                        }*/

            $this->checkOnAppPayment($client);

            $this->sendLowBalanceEmail($client, $lastNewBalance, $lastNewBalanceCurrencyID, $lastNewBalanceDate);
        }

        echo PHP_EOL . 'Itemized cron job finished' . PHP_EOL;
    }

    private function getClientCustomInfo($client, $key = '')
    {
        $query = "SELECT
                    custom_info
                FROM
                    tblonappusers
                WHERE
                    service_id = " . $client['service_id'];

        $result = full_query($query);
        if (!$result) {
            return '';
        }
        $queryRes = mysql_fetch_assoc($result);
        $infoJSON = $queryRes['custom_info'];

        if (!$infoJSON || !is_string($infoJSON)) {
            return '';
        }

        $infoData = json_decode($infoJSON, true);

        if (!is_array($infoData)) {
            return '';
        }

        if (!$key) {
            return $infoData;
        }

        if (!isset($infoData[$key])) {
            return '';
        }

        return $infoData[$key];
    }

    private function setClientCustomInfo($client, $key, $value)
    {
        $infoData = $this->getClientCustomInfo($client);

        if (!$infoData) {
            $infoData = array();
        }

        $infoData[$key] = $value;

        full_query(
            "UPDATE
                tblonappusers
             SET
                custom_info = '" . json_encode($infoData) . "'
             WHERE
                service_id = " . $client['service_id']
        );
    }

    private function sendLowBalanceEmail($client, $lastNewBalance, $lastNewBalanceCurrencyID, $lastNewBalanceDate)
    {
        global $_LANG;

        if ($lastNewBalance <= 0) {
            return;
        }

        $lowBalanceEmailTimestamp = (int) $this->getClientCustomInfo($client, 'lowBalanceEmailTimestamp');

        if (time() - $lowBalanceEmailTimestamp < 24 * 3600) {
            return;
        }

        $sumForLastPeriodFromHourlyStat = $this->getSumForLastPeriodFromHourlyStat($client, $lastNewBalanceDate);
        if ($lastNewBalance < $sumForLastPeriodFromHourlyStat) {
            sendmessage($_LANG['onappuserslowbalanceemail'], $client['service_id']);
            $this->setClientCustomInfo($client, 'lowBalanceEmailTimestamp', time());
        }
    }

    private function checkOnAppPayment($client)
    {
        $qry = 'SELECT
                    sum(cost) as amount 
                FROM
                    `tblonappusers_Hourly_Stat`
                WHERE
                    `client_id` = :client_id
                    AND `server_id` = :server_id
                    AND `onapp_user_id` = :onapp_user_id';

        $qry = str_replace(
            array(':server_id', ':client_id', ':onapp_user_id'),
            array(
                $client['server_id'],
                $client['client_id'],
                $client['onapp_user_id'],
            ),
            $qry
        );

        $dateRes = mysql_query($qry);
        if (($dateRes === false) || (mysql_num_rows($dateRes) == 0)) {
            return;
        }

        $statArr = mysql_fetch_assoc($dateRes);

        if (!$statArr['amount']) {
            return;
        }

        $prePayPaymentID = $this->getOnAppPrePayPaymentID($client);

        $this->createOrUpdatePayment($client, $statArr['amount'], $prePayPaymentID);
    }

    private function getSumForLastPeriodFromHourlyStat($client, $date)
    {
        $qry = 'SELECT
                    sum(cost) as amount 
                FROM
                    `tblonappusers_Hourly_Stat`
                WHERE
                    `client_id` = :client_id
                    AND `server_id` = :server_id
                    AND `onapp_user_id` = :onapp_user_id
                    AND `end_date` > SUBDATE(":date", INTERVAL ' . self::LOW_CREDITS_BALANCE_LEVEL_DAYS . ' DAY)';

        $qry = str_replace(
            array(':server_id', ':client_id', ':onapp_user_id', ':date'),
            array(
                $client['server_id'],
                $client['client_id'],
                $client['onapp_user_id'],
                $date
            ),
            $qry
        );

        $dateRes = mysql_query($qry);
        if (($dateRes === false) || (mysql_num_rows($dateRes) == 0)) {
            return;
        }

        $statArr = mysql_fetch_assoc($dateRes);

        return (float) $statArr['amount'];
    }

    private function checkIfItWasntCharged($client, $startDate, $endDate)
    {
        $qry = 'SELECT
                    cost 
                FROM
                    `tblonappusers_Hourly_Stat`
                WHERE
                    `client_id` = :client_id
                    AND `server_id` = :server_id
                    AND `onapp_user_id` = :onapp_user_id
                    AND `start_date` = ":start_date"
                    AND `end_date` = ":end_date"';

        $qry = str_replace(
            array(':server_id', ':client_id', ':onapp_user_id', ':start_date', ':end_date'),
            array(
                $client['server_id'],
                $client['client_id'],
                $client['onapp_user_id'],
                $startDate,
                $endDate
            ),
            $qry
        );

        $dateRes = mysql_query($qry);

        return !(($dateRes === false) || (mysql_num_rows($dateRes) == 0));
    }

    protected function getOnAppPrePayPaymentID($client)
    {
        $paymentObj = $this->getAuthedOnAppObj($client)->factory('Payment');
        $paymentObj->_payer_type = 'user';
        $paymentObj->_user_id = $client['onapp_user_id'];

        $payments = $paymentObj->getList();
        if (!$payments) {
            return null;
        }

        $prePayPaymentID = null;
        foreach ($payments as $key => $payment) {
            if ($payment->_payer_id != $client['onapp_user_id']) {
                continue;
            }
            if (stripos($payment->_invoice_number, self::INVOICE_NUMBER_PREFIX) === 0) {
                $prePayPaymentID = $payment->_id;
                break;
            }
        }

        return $prePayPaymentID;
    }

    private function createOrUpdatePayment($client, $amount, $paymentID = null)
    {
        $paymentObj = $this->getAuthedOnAppObj($client)->factory('Payment');
        if ($paymentID) {
            $paymentObj->_id = $paymentID;
        }
        $paymentObj->_payer_type = 'user';
        $paymentObj->_user_id = $paymentObj->_payer_id = $client['onapp_user_id'];
        $paymentObj->_invoice_number = self::INVOICE_NUMBER_PREFIX . '-' . date('d-m-Y-H-i-s');

        $paymentObj->_amount = $amount;

        $paymentObj->save();
    }

    private function getLastStatRetrievingDate($client)
    {
        if (isset($this->cliOptions->since)) {
            return $this->cliOptions->since;
        }

        $qry = 'SELECT
                    `date`
                FROM
                    `tblonappusers_Hourly_LastCheck`
                WHERE
                    `client_id` = :client_id
                    AND `server_id` = :server_id
                    AND `onapp_user_id` = :onapp_user_id';

        $qry = str_replace(
            array(':server_id', ':client_id', ':onapp_user_id'),
            array(
                $client['server_id'],
                $client['client_id'],
                $client['onapp_user_id'],
            ),
            $qry
        );

        $dateRes = mysql_query($qry);
        if (($dateRes === false) || (mysql_num_rows($dateRes) == 0)) {
            $date = gmdate('Y-m-01 00:00:00');
        } else {
            $dateArr = mysql_fetch_assoc($dateRes);

            $date = date('Y-m-d H:00:00', strtotime($dateArr['date']) /*- (1 * 3600)*/);
        }

        return $date;
    }

    public function chargeClient($client, $amount, $startDate, $endDate)
    {
        global $CONFIG;

        if ($this->checkIfItWasntCharged($client, $startDate, $endDate)) {
            return array(
                'status' => true,
                'new_balance' => 0,
            );
        }

        $command = 'addcredit';
        $values['clientid'] = $client['client_id'];
        $values['description'] = 'Hourly bill user #' .
            $client['WHMCSUserID'] .
            ' | Period: ' .
            $startDate .
            ' - ' .
            $endDate .
            ' | Billed ' .
            date('d/m/Y H:i');
        $values['amount'] = -($amount * $client['rate']);

        $this->log($this->getAdminUsername(), 'addcredit admin');
        $this->log(print_r($values, true), 'addcredit params');

        $results = localAPI($command, $values, $this->getAdminUsername());

        if (!isset($results['result']) || $results['result'] !== 'success') {
            $this->log(print_r($results, true), '[ERROR] addcredit call failed');

            return array(
                'status' => false,
                'new_balance' => 0,
            );
        }

        $this->log(print_r($results, true), 'addcredit call result');

        if (isset($results['newbalance']) && $results['newbalance'] <= 0) {
            if ($CONFIG['AutoTermination'] === 'on') {
                // todo check query
                $qry = 'UPDATE
                            tblhosting
                        SET
                            nextduedate = DATE_ADD( NOW(), INTERVAL :days DAY )
                        WHERE
                            id = :serviceID';
                $qry = str_replace(':days', $CONFIG['AutoTerminationDays'], $qry);
                $qry = str_replace(':serviceID', $client['service_id'], $qry);
                full_query($qry);
            }

            $this->suspendUser($client);

            $this->log('client suspended');

            return array(
                'status' => true,
                'new_balance' => (float) $results['newbalance'],
            );
        }

        return array(
            'status' => true,
            'new_balance' => (float) $results['newbalance'],
        );
    }

    private function suspendUser($client)
    {
        if (!function_exists('serversuspendaccount')) {
            require_once $this->root . 'includes/modulefunctions.php';
        }
        $result = serversuspendaccount($client['service_id']);
        if ($result !== 'success') {
            $this->log('Can not suspend account (service id: ' . $client['service_id'] . '): ' . $result);

            return false;
        }

        return true;
    }

    private function unsuspendUser($client)
    {
        if (!function_exists('serverunsuspendaccount')) {
            require_once $this->root . 'includes/modulefunctions.php';
        }
        $result = serverunsuspendaccount($client['service_id']);
        if ($result !== 'success') {
            $this->log('Can not unsuspend account (service id: ' . $client['service_id'] . '): ' . $result);

            return false;
        }

        return true;
    }

    private function saveHourlyStat($client, $totalCost, $startDate, $endDate)
    {
        $qry = 'INSERT INTO
                            `tblonappusers_Hourly_Stat` (
                                 `server_id`,
                                 `client_id`,
                                 `onapp_user_id`,
                                 `cost`,
                                 `start_date`,
                                 `end_date`
                            )
                            VALUES (
                                :server_id,
                                :client_id,
                                :onapp_user_id,
                                :cost,
                                ":start_date",
                                ":end_date"
                            )';

        $qry = str_replace(
            array(':server_id', ':client_id', ':onapp_user_id', ':cost', ':start_date', ':end_date'),
            array(
                $client['server_id'],
                $client['client_id'],
                $client['onapp_user_id'],
                $totalCost,
                $startDate,
                $endDate,
            ),
            $qry
        );

        full_query($qry);
    }

    private function saveLastCheckDate($client, $date)
    {
        $qry = 'SELECT
                    `date`
                FROM
                    `tblonappusers_Hourly_LastCheck`
                WHERE
                    `server_id` = :server_id AND
                    `client_id` = :client_id AND 
                    `onapp_user_id` = :onapp_user_id';
        $qry = str_replace(
            array(':server_id', ':client_id', ':onapp_user_id'),
            array(
                $client['server_id'],
                $client['client_id'],
                $client['onapp_user_id'],
            ),
            $qry
        );
        $dateQry = mysql_query($qry);
        if (($dateQry === false) || (mysql_num_rows($dateQry) == 0)) {
            $qry = 'INSERT INTO
                            `tblonappusers_Hourly_LastCheck` (
                                 `server_id`,
                                 `client_id`,
                                 `onapp_user_id`,
                                 `date`
                            )
                            VALUES (
                                :server_id,
                                :client_id,
                                :onapp_user_id,
                                ":date"
                            )';
        } else {
            $qry = 'UPDATE
                    tblonappusers_Hourly_LastCheck
                SET
                    date = ":date" 
                WHERE
                    `server_id` = :server_id AND
                    `client_id` = :client_id AND 
                    `onapp_user_id` = :onapp_user_id';
        }
        $qry = str_replace(
            array(':server_id', ':client_id', ':onapp_user_id', ':date'),
            array(
                $client['server_id'],
                $client['client_id'],
                $client['onapp_user_id'],
                $date,
            ),
            $qry
        );
        full_query($qry);
    }
}

new OnApp_UserModule_Cron_Hourly;