<?php

ini_set( 'memory_limit', '512M' );
ini_set( 'max_execution_time', 0 );
set_time_limit( 0 );

class OnApp_UserModule_Cron {
    private $root;
    private $clients;
    private $fromDate;
    private $tillDate;
    private $fromDateUTC;
    private $tillDateUTC;
    private $timeZoneOffset;
    private $dueDate;
    private $cliOptions;
    private $logFile;
    private $invoicesFile;
    private $testMode     = true;
    private $logEnabled   = false;
    private $printEnabled = false;
    private $servers      = array();
    private $log          = array();

    public function __construct() {
        $this->checkCLIMode();
        $this->root = realpath( dirname( dirname( dirname( dirname( dirname( $_SERVER[ 'argv' ][ 0 ] ) ) ) ) ) ) . DIRECTORY_SEPARATOR;
        $this->getRequiredFiles();
        $this->prepareLogFiles();
        $this->setCLIoptions();
        $this->getServers();
        $this->getClients();
        $this->calculateDates();
        $this->process();
    }

    private function process() {
        //get admin
        $qry   = 'SELECT
                    `username`
                FROM
                    `tbladmins`
                LIMIT 1';
        $res   = mysql_query( $qry );
        $admin = mysql_result( $res, 0 );

        //calculate invoice due date
        $this->dueDate = date( 'Ymd' );

        while( $client = mysql_fetch_assoc( $this->clients ) ) {
            $clientAmount = $this->getAmount( $client );
            if( ! $clientAmount ) {
                $this->writeLog( 'false returned' );
                continue;
            }

            if( $clientAmount->total_cost >= 0.01 ) {
                $data = $this->generateInvoiceData( $clientAmount, $client );
                if( $data == false ) {
                    $this->writeInvoices( array_merge( $client, $data ) );
                    continue;
                }

                $this->writeLog( array_merge( $client, $data ) );

                if( $this->testMode ) {
                    $this->writeInvoices( array_merge( $client, $data ) );
                    continue;
                }

                $result = localAPI( 'CreateInvoice', $data, $admin );
                if( $result[ 'result' ] != 'success' ) {
                    $this->writeLog( 'An Error occurred trying to create a invoice: ' . $result[ 'result' ] );
                    $this->writeLog( $result );
                    logactivity( 'An Error occurred trying to create a invoice: ' . $result[ 'result' ] );
                }
                else {
                    $this->writeLog( $result );
                    $table  = 'tblinvoiceitems';
                    $update = array(
                        'type'          => 'OnAppUsers',
                        'duedate'       => $this->dueDate,
                        'relid'         => $client[ 'serviceID' ],
                        'paymentmethod' => $client[ 'paymentmethod' ],
                    );
                    $where = array(
                        'invoiceid' => $result[ 'invoiceid' ]
                    );
                    update_query( $table, $update, $where );
                }
            }
            else {
                $this->writeLog( (array)$clientAmount );
                $this->writeLog( ' Amount is less then 0.01, invoice will not be generated' );
            }
        }
    }

    private function getAmount( array $user ) {
        $tmp = str_repeat( '=', 80 ) . PHP_EOL;
        $tmp .= 'WHMCS user ID: ' . $user[ 'WHMCSUserID' ] . PHP_EOL;
        $tmp .= 'OnApp user ID: ' . $user[ 'OnAppUserID' ] . PHP_EOL;
        $tmp .= 'Server ID: ' . $user[ 'serverID' ];
        $this->writeLog( $tmp );

        $date = array(
            'period[startdate]' => $this->fromDateUTC,
            'period[enddate]'   => $this->tillDateUTC,
        );
        $data = $this->getResourcesData( $user, $date );

        if( ! $data ) {
            return false;
        }

        $data = $data->user_stat;

        $unset = array(
            'vm_stats',
            'stat_time',
            'user_resources_cost',
            'currency_code',
            'user_id',
        );
        foreach( $data as $key => &$value ) {
            if( in_array( $key, $unset ) ) {
                unset( $data->$key );
            }
            else {
                $data->$key *= $user[ 'rate' ];
            }
        }

        return $data;
    }

    private function getResourcesData( $client, $date ) {
        $date = http_build_query( $date );

        $url  = $this->servers[ $client[ 'serverID' ] ][ 'address' ] . '/users/' . $client[ 'OnAppUserID' ] . '/user_statistics.json?' . $date;
        $data = $this->sendRequest( $url, $this->servers[ $client[ 'serverID' ] ][ 'username' ], $this->servers[ $client[ 'serverID' ] ][ 'password' ] );

        if( $data ) {
            return json_decode( $data );
        }
        else {
            return false;
        }
    }

    private function sendRequest( $url, $user, $password ) {
        $this->writeLog( 'API request: ' . $url );

        $curl = new CURL();
        $curl->addOption( CURLOPT_USERPWD, $user . ':' . $password );
        $curl->addOption( CURLOPT_HTTPHEADER, array( 'Accept: application/json', 'Content-type: application/json' ) );
        $curl->addOption( CURLOPT_HEADER, true );
        $data = $curl->get( $url );

        if( $curl->getRequestInfo( 'http_code' ) != 200 ) {
            $e = 'ERROR: ' . PHP_EOL;
            $e .= "\trequest URL:\t\t" . $url . PHP_EOL;
            $e .= "\trequest response:\t" . $curl->getRequestInfo( 'response_body' ) . PHP_EOL;
            $this->writeLog( $e );
            return false;
        }
        else {
            return $data;
        }
    }

    private function getClients() {
        $sql = 'SELECT
                    tblclients.`taxexempt`,
                    tblclients.`state`,
                    tblclients.`country`,
                    tblclients.`currency`,
                    tblclients.`language`,
                    tblcurrencies.`rate`,
                    tblcurrencies.`suffix`,
                    tblhosting.`paymentmethod`,
                    tblhosting.`domain`,
                    tblhosting.`id` AS serviceID,
                    tblhosting.`server` AS serverID,
                    tblhosting.`userid` AS WHMCSUserID,
                    tblcustomfieldsvalues.`value` AS OnAppUserID,
                    tblproducts.`tax`,
                    tblproducts.`name` AS packagename
                FROM
                    tblhosting
                LEFT JOIN tblproducts ON
                    tblhosting.`packageid` = tblproducts.`id`
                    AND tblproducts.`servertype` = BINARY "OnAppUsers"
                LEFT JOIN tblclients ON
                    tblclients.`id` = tblhosting.`userid`
                LEFT JOIN tblcurrencies ON
                    tblcurrencies.`id` = tblclients.`currency`
                LEFT JOIN
                    tblcustomfields ON
                    tblcustomfields.`relid` = tblhosting.`packageid`
                    AND tblcustomfields.`fieldname` = BINARY "OnApp user ID"
                LEFT JOIN
                    tblcustomfieldsvalues ON
                    tblcustomfieldsvalues.`fieldid` = tblcustomfields.`id`
                WHERE
                    tblhosting.`domainstatus` IN ( "Active", "Suspended" )
                    AND tblproducts.`name` IS NOT NULL
                    ORDER BY `OnAppUserID`';
        $this->clients = full_query( $sql );
    }

    private function getServers() {
        $sql = 'SELECT
                    `id`,
                    `secure`,
                    `username`,
                    `hostname`,
                    `password`,
                    `ipaddress`
                FROM
                    tblservers
                WHERE
                    type = BINARY "OnAppUsers"';
        $result = full_query( $sql );
        while( $server = mysql_fetch_assoc( $result ) ) {
            $server[ 'password' ] = decrypt( $server[ 'password' ] );
            if( $server[ 'secure' ] ) {
                $server[ 'address' ] = 'https://';
            }
            else {
                $server[ 'address' ] = 'http://';
            }
            if( empty( $server[ 'ipaddress' ] ) ) {
                $server[ 'address' ] .= $server[ 'hostname' ];
            }
            else {
                $server[ 'address' ] .= $server[ 'ipaddress' ];
            }
            unset( $server[ 'ipaddress' ], $server[ 'hostname' ], $server[ 'secure' ] );
            $this->servers[ $server[ 'id' ] ] = $server;
        }
    }

    private function generateInvoiceData( $data, array $client ) {
        global $_LANG;

        // load client language
        $langFile = dirname( __DIR__ ) . '/lang/' . $client[ 'language' ] . '.php';
        if( ! file_exists( $langFile ) ) {
            $langFile = dirname( __DIR__ ) . '/lang/english.php';
        }
        require $langFile;

        //check if invoice should be generated
        $fromTime = strtotime( $this->fromDate );
        $tillTime = strtotime( $this->tillDate );
        if( $fromTime >= $tillTime ) {
            return false;
        }

        //check if the item should be taxed
        $taxed = empty( $client[ 'taxexempt' ] ) && (int)$client[ 'tax' ];
        if( $taxed ) {
            $taxrate = getTaxRate( 1, $client[ 'state' ], $client[ 'country' ] );
            $taxrate = $taxrate[ 'rate' ];
        }
        else {
            $taxrate = '';
        }

        $timeZone = ' UTC' . ( $this->timeZoneOffset >= 0 ? '+' : '' ) . ( $this->timeZoneOffset / 3600 );

        $this->fromDate     = date( $_LANG[ 'OnApp_Users_Stat_DateFormat' ], $fromTime );
        $this->tillDate     = date( $_LANG[ 'OnApp_Users_Stat_DateFormat' ], $tillTime );
        $invoiceDescription = array(
            $_LANG[ 'OnApp_Users_Invoice_Product' ] . $client[ 'packagename' ],
            $_LANG[ 'OnApp_Users_Invoice_Period' ] . $this->fromDate . ' - ' . $this->tillDate . $timeZone,
        );
        $invoiceDescription = implode( PHP_EOL, $invoiceDescription );

        $return = array(
            'userid'           => $client[ 'WHMCSUserID' ],
            'date'             => $this->dueDate,
            'duedate'          => $this->dueDate,
            'paymentmethod'    => $client[ 'paymentmethod' ],
            'taxrate'          => $taxrate,
            'sendinvoice'      => true,
            'itemdescription1' => $invoiceDescription,
            'itemamount1'      => 0,
            'itemtaxed1'       => $taxed,
        );

        unset( $data->total_cost );
        $i = 1;
        foreach( $data as $key => $value ) {
            if( $value > 0 ) {
                $tmp    = array(
                    'itemdescription' . ++ $i => $_LANG[ 'OnApp_Users_Invoice_' . $key ],
                    'itemamount' . $i         => $value,
                    'itemtaxed' . $i          => $taxed,
                );
                $return = array_merge( $return, $tmp );
            }
        }

        return $return;
    }

    private function getRequiredFiles() {
        global $whmcsmysql, $cc_encryption_hash, $templates_compiledir, $CONFIG, $_LANG, $whmcs;

        if( file_exists( $this->root . 'init.php' ) ) {
            require_once $this->root . 'init.php';
        }
        else {
            require_once $this->root . 'dbconnect.php';
            include_once $this->root . 'includes/functions.php';
        }

        require_once dirname( __DIR__ ) . '/includes/php/CURL.php';
        require_once $this->root . '/modules/servers/OnAppUsers/includes/php/SOP.php';
        include_once $this->root . 'includes/processinvoices.php';
        include_once $this->root . 'includes/invoicefunctions.php';

        error_reporting( E_ALL ^ E_NOTICE );
        ini_set( 'display_errors', 'On' );
        ini_set( 'memory_limit', '512M' );
        ini_set( 'max_execution_time', 0 );
        set_time_limit( 0 );
    }

    private function checkCLIMode() {
        if( PHP_SAPI != 'cli' ) {
            if( ! empty( $_SERVER[ 'REMOTE_ADDR' ] ) ) {
                exit( 'Not allowed!' . PHP_EOL );
            }
        }
    }

    private function calculateDates() {
        $tmp                  = time();
        $this->timeZoneOffset = strtotime( date( 'Y-m-d H:i:s', $tmp ) ) - strtotime( gmdate( 'Y-m-d H:i:s', $tmp ) );

        if( isset( $this->cliOptions->since ) ) {
            $fromDate = $this->cliOptions->since;
        }
        else {
            $fromDate = date( 'Y-m-d 00:00', strtotime( 'first day of last month' ) );
        }
        if( isset( $this->cliOptions->till ) ) {
            $tillDate = $this->cliOptions->till;
        }
        else {
            $tillDate = date( 'Y-m-d 00:00', strtotime( 'first day of next month', strtotime( $fromDate ) ) );
        }

        $this->fromDateUTC = $this->getUTCTime( $fromDate, 'Y-m-d H:30' );
        $this->tillDateUTC = $this->getUTCTime( $tillDate, 'Y-m-d H:30' );

        $this->writeLog( 'Run at ' . date( 'Y-m-d H:i' ) );
        $this->writeLog( 'UTC time from' . $this->fromDateUTC );
        $this->writeLog( 'UTC time till' . $this->tillDateUTC . PHP_EOL );

        $this->fromDate = $fromDate;
        $this->tillDate = $tillDate;
    }

    private function getUTCTime( $date, $format = 'Y-m-d H:i' ) {
        return gmdate( $format, strtotime( $date ) );
    }

    private function setCLIOptions() {
        $options = array(
            'since'    => array(
                'description' => 'date to start',
                'validation'  => '^(20\d{2})-([0][1-9]|[1][0-2])-([\d]{2}) ([0-1][0-9]|[2][0-3]):([0-5][0-9])$',
                'short'       => 's',
            ),
            'till'     => array(
                'description' => 'date to finish',
                'validation'  => '^(20\d{2})-([0][1-9]|[1][0-2])-([\d]{2}) ([0-1][0-9]|[2][0-3]):([0-5][0-9])$',
                'short'       => 't',
            ),
            'log'      => array(
                'description' => 'log data to file',
                'short'       => 'l',
            ),
            'print'    => array(
                'description' => 'print data to screen',
                'short'       => 'p',
            ),
            'generate' => array(
                'description' => 'generate invoices',
                'short'       => 'g',
            ),
        );

        $options = new SOP( $options );
        $options->setBanner( 'OnApp User Module invoices processor' );
        $this->cliOptions = $options->parse();

        if( isset( $this->cliOptions->log ) ) {
            $this->logEnabled = true;
        }
        if( isset( $this->cliOptions->print ) ) {
            $this->printEnabled = true;
        }
        // force test mode
        if( isset( $this->cliOptions->generate ) ) {
            $this->testMode = false;
        }
    }

    private function prepareLogFiles() {
        $this->logFile      = __DIR__ . '/logs/data.log';
        $this->invoicesFile = __DIR__ . '/logs/invoices.log';
        $this->checkLogFiles();
    }

    private function checkLogFiles() {
        $logFiles = array(
            $this->logFile,
            $this->invoicesFile,
        );
        foreach( $logFiles as $logFile ) {
            if( @file_put_contents( $logFile, '' ) === false ) {
                exit( '  Can\'t write log file. Check if file ' . $logFile . ' exists and is writable!' . PHP_EOL );
            }
        }
    }

    private function writeLog( $data ) {
        if( is_array( $data ) ) {
            $data = print_r( $data, true );
        }
        $data .= PHP_EOL;
        if( $this->logEnabled ) {
            file_put_contents( $this->logFile, $data, FILE_APPEND );
        }
        if( $this->printEnabled ) {
            echo $data;
        }
    }

    private function writeInvoices( array $data ) {
        if( empty( $data[ 'taxrate' ] ) ) {
            $data[ 'taxrate' ] = 0;
        }

        $tmp = 'Service #: ' . $data[ 'serviceID' ] . PHP_EOL;
        $tmp .= 'WHMCS user #: ' . $data[ 'WHMCSUserID' ] . PHP_EOL;
        $tmp .= 'Tax: ' . $data[ 'taxrate' ] . '%' . PHP_EOL;
        $tmp .= 'Payment method: ' . $data[ 'paymentmethod' ] . PHP_EOL . PHP_EOL;

        $total = 0;
        for( $i = 1; $i < 100; $i ++ ) {
            if( ! isset( $data[ 'itemdescription' . $i ] ) ) {
                break;
            }
            else {
                $tmp .= $data[ 'itemdescription' . $i ];
                $tmp .= ':    ' . $data[ 'itemamount' . $i ] . $data[ 'suffix' ] . PHP_EOL;
                $total += $data[ 'itemamount' . $i ];
            }
        }
        $tmp .= str_repeat( '-', 40 ) . PHP_EOL;
        $tmp .= 'Sub total: ' . $total . $data[ 'suffix' ] . PHP_EOL;
        $tmp .= 'Taxes: ' . ( $tax = $total / 100 * $data[ 'taxrate' ] ) . $data[ 'suffix' ] . PHP_EOL;
        $tmp .= 'Total: ' . ( $total + $tax ) . $data[ 'suffix' ] . PHP_EOL;
        $tmp .= str_repeat( '=', 80 ) . PHP_EOL . PHP_EOL;

        file_put_contents( $this->invoicesFile, $tmp, FILE_APPEND );
    }

    public function __destruct() {
        if( $this->logEnabled ) {
            echo PHP_EOL, ' Log has been saved in ' . $this->logFile, PHP_EOL;
        }
        if( $this->testMode ) {
            echo PHP_EOL, ' Invoices have been saved in ' . $this->invoicesFile, PHP_EOL;
        }
    }
}

new OnApp_UserModule_Cron;