<?php

require __DIR__ . '/common.php';

class OnApp_UserModule_Cron_Invoices_Test extends OnApp_UserModule_Cron {
    const TYPE = 'test';

    protected function run() {
        $this->getAdditionalFiles();
        $this->process();
    }

    private function process() {
        //calculate invoice due date
        $this->dueDate = date( 'Ymd' );
        $tab = "\n\t\t\t";

        while( $client = mysql_fetch_assoc( $this->clients ) ) {
            $clientAmount = $this->getAmmount( $client );

            if( ! is_null( $clientAmount->total ) ) {
                $data = $this->generateInvoiceData( $clientAmount, $client );
                if( $data == false ) {
                    continue;
                }

                $total = 0;
                foreach( $data as $key => $value ) {
                    if( strpos( $key, 'itemamount' ) === false ) {
                        continue;
                    }
                    $total += $value;
                }

                $tmp = PHP_EOL;
                $tmp .= 'WHMCS user ID: ' . $client[ 'client_id' ] . PHP_EOL;
                $tmp .= 'OnApp user ID: ' . $client[ 'onapp_user_id' ] . PHP_EOL;
                $tmp .= 'Server ID: ' . $client[ 'server_id' ] . PHP_EOL . PHP_EOL;
                $tmp .= $data[ 'itemdescription1' ] . PHP_EOL;
                $tmp .= 'Total: ' . $total . PHP_EOL;
                $tmp .= 'Item will be taxed: ' . $data[ 'taxrate' ] . '%' . PHP_EOL . PHP_EOL;
                $tmp .= 'Data: ' . print_r( $data, true );
                $tmp = implode( $tab, explode( PHP_EOL, $tmp ) );

                $this->log[ 'data' ][ ] = $tmp;
            }
        }
    }

    private function getAdditionalFiles() {
        include_once $this->root . 'includes/processinvoices.php';
        include_once $this->root . 'includes/invoicefunctions.php';
    }
}
new OnApp_UserModule_Cron_Invoices_Test;