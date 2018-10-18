<?php

require __DIR__ . '/common.php';

class OnApp_UserModule_Cron_Invoices_Test extends OnApp_UserModule_Cron
{
    const TYPE = 'test';

    protected function run()
    {
        $this->log($this->fromDateUTC, 'UTC time from');
        $this->log($this->tillDateUTC, 'UTC time till');

        $this->process();
    }

    private function process()
    {
        //calculate invoice due date
        $this->dueDate = date('Ymd');
        $tab = "\n\t\t\t";

/*        if (!function_exists('serversuspendaccount')) {
            require_once $this->root . 'includes/modulefunctions.php';
        }
        $result = serversuspendaccount(15);

        print_r($result);

        exit;*/

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
                $data = $this->generateInvoiceData($clientAmount, $client);
                if ($data == false) {
                    continue;
                }

                $total = 0;
                foreach ($data as $key => $value) {
                    if (strpos($key, 'itemamount') === false) {
                        continue;
                    }
                    $total += $value;
                }

                $tmp = PHP_EOL;
                $tmp .= $data['itemdescription1'] . PHP_EOL;
                $tmp .= 'Total: ' . $total . PHP_EOL;
                $tmp .= 'Item will be taxed: ' . $data['taxrate'] . '%' . PHP_EOL . PHP_EOL;
                $tmp .= 'Data: ' . print_r($data, true);
                $tmp = implode($tab, explode(PHP_EOL, $tmp));

                $this->log[] = $tmp;
                $this->log[] = '========== SPLIT =============';
            }
        }
    }
}

new OnApp_UserModule_Cron_Invoices_Test;