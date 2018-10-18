<?php

require __DIR__ . '/common.php';

class OnApp_UserModule_Cron_Invoices extends OnApp_UserModule_Cron
{
    const TYPE = 'invoices';

    protected function run()
    {
        $this->log($this->fromDateUTC, 'UTC time from');
        $this->log($this->tillDateUTC, 'UTC time till');

        $this->process();
    }

    private function process()
    {
        //get admin
        $qry = 'SELECT
                    `username`
                FROM
                    `tbladmins`
                WHERE
                    disabled = 0
                LIMIT 1';
        $res = mysql_query($qry);
        //$admin = mysql_result( $res, 0 );
        $adminArr = mysql_fetch_assoc($res);
        $admin = $adminArr['username'];

        //calculate invoice due date
        $this->dueDate = date('Ymd');

        while ($client = mysql_fetch_assoc($this->clients)) {
            if ($client['billing_type'] !== 'postpaid') {
                continue;
            }

            $this->log('WHMCS user ID: ' . $client['client_id']);
            $this->log('OnApp user ID: ' . $client['onapp_user_id']);
            $this->log('Server ID: ' . $client['server_id']);

            $clientAmount = $this->getAmount($client);

            if (!$clientAmount) {
                continue;
            }

            if ($clientAmount->total_cost > 0) {
                $client['dueDate'] = htmlspecialchars_decode($client['dueDate']);
                $client['dueDate'] = json_decode($client['dueDate']);
                $server_id = $client['server_id'];
                $client['dueDate'] = $client['dueDate']
                    ->DueDateCurrent
                    ->$server_id;

                $data = $this->generateInvoiceData($clientAmount, $client);
                if ($data == false) {
                    continue;
                }

                $this->log($data);

                $result = localAPI('CreateInvoice', $data, $admin);
                if ($result['result'] != 'success') {
                    $this->log($result['result'], 'An Error occurred trying to create a invoice');
                    $this->log(print_r($result, true));

                    logactivity('An Error occurred trying to create a invoice: ' . $result['result']);
                } else {
                    $this->log(print_r($result, true));

                    $qry = 'UPDATE
                                `tblinvoiceitems`
                            SET
                                `relid` = :WHMCSServiceID,
                                `type` = "onappusers"
                            WHERE
                                `invoiceid` = :invoiceID';
                    $qry = str_replace(':WHMCSServiceID', $client['service_id'], $qry);
                    $qry = str_replace(':invoiceID', $result['invoiceid'], $qry);
                    full_query($qry);

                    $table = 'tblonappusers_invoices';
                    $values = array(
                        'id' => $result['invoiceid'],
                        'amount' => $this->dataTMP->total_cost
                    );
                    insert_query($table, $values);

                    $getInvoiceData = array(
                        'invoiceid' => $result['invoiceid'],
                    );
                    $getInvoiceResult = localAPI('GetInvoice', $getInvoiceData, $admin);
                    if ($getInvoiceResult['result'] == 'success') {
                        if ($getInvoiceResult['status'] == "Paid") {
                            if (function_exists('hook_onappusers_InvoicePaid')) {
                                $vars['invoiceid'] = $result['invoiceid'];
                                hook_onappusers_InvoicePaid($vars);
                                $this->log(print_r($getInvoiceResult, true));
                            }
                        }
                    }
                    $this->log('========== SPLIT =============');
                }
            }
        }
    }
}

new OnApp_UserModule_Cron_Invoices;