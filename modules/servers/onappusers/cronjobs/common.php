<?php

ini_set( 'max_execution_time', 1800 );
@ini_set( 'memory_limit', '512M' );
@ini_set( 'max_execution_time', 0 );
@set_time_limit( 0 );

abstract class OnApp_UserModule_Cron {
    protected $root;
    protected $clients;
    protected $fromDate;
    protected $tillDate;
    protected $fromDateUTC;
    protected $tillDateUTC;
    protected $timeZoneOffset;
    protected $dueDate;
    protected $cliOptions;
    protected $logEnabled   = false;
    protected $printEnabled = false;
    protected $servers      = array();
    protected $log          = array();

    abstract protected function run();

    public function __construct() {
        $this->checkCLIMode();
        $this->root = realpath( dirname( dirname( dirname( dirname( dirname( $_SERVER[ 'argv' ][ 0 ] ) ) ) ) ) ) . DIRECTORY_SEPARATOR;

        $this->getRequiredFiles();
        $this->setCLIoptions();
        $this->checkSQL();
        $this->getServers();
        $this->getClients();

        if( $this->logEnabled ) {
            $this->log[ 'Run at' ] = date( 'Y-m-d H:i:s' );
        }

        $this->calculateDates();
        $this->run();
    }

    protected function getAmmount( array $user ) {
        $user[ 'rate' ] = 1;
        $qry = 'SELECT
                    SUM( `total_cost` ) * :rate AS total,
                    SUM( `backup_cost` ) * :rate AS backup,
                    SUM( `edge_group_cost` ) * :rate AS edgecdn,
                    SUM( `monit_cost` ) * :rate AS monitis,
                    SUM( `storage_disk_size_cost` ) * :rate AS `storage`,
                    SUM( `template_cost` ) * :rate AS templates,
                    SUM( `vm_cost` ) * :rate AS vm,
                    SUM( `user_resources_cost` ) * :rate AS resources,
                    MAX( `date` ) AS `date`
                FROM
                    `onapp_itemized_resources`
                WHERE
                    `whmcs_user_id` = :WHMCSUserID
                    AND `onapp_user_id` = :OnAppUserID
                    AND `server_id` = :serverID
                    AND `date` BETWEEN ":dateFrom"
                    AND ":dateTill"';
        $qry = str_replace( ':WHMCSUserID', $user[ 'client_id' ], $qry );
        $qry = str_replace( ':OnAppUserID', $user[ 'onapp_user_id' ], $qry );
        $qry = str_replace( ':serverID', $user[ 'server_id' ], $qry );
        $qry = str_replace( ':dateFrom', $this->fromDateUTC, $qry );
        $qry = str_replace( ':dateTill', $this->tillDateUTC, $qry );
        $qry = str_replace( ':rate', $user[ 'rate' ], $qry );
        $data = mysql_fetch_assoc( full_query( $qry ) );

        if( $this->printEnabled ) {
            echo $qry, PHP_EOL, PHP_EOL;
        }

        return (object)$data;
    }

    protected function getClients() {
        $clients_query = 'SELECT
            tblclients.taxexempt,
            tblclients.state,
            tblclients.country,
            tblclients.currency,
            tblcurrencies.rate,
            tblhosting.paymentmethod,
            tblhosting.domain,
            tblhosting.id AS service_id,
            tblonappusers.server_id,
            tblonappusers.client_id,
            tblonappusers.onapp_user_id,
            tblproducts.tax,
            tblproducts.name AS packagename,
            tblproducts.configoption1 AS dueDate
        FROM
            tblonappusers
        LEFT JOIN tblhosting ON
            tblhosting.userid = tblonappusers.client_id
            AND tblhosting.server = tblonappusers.server_id
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
            ORDER BY tblonappusers.`onapp_user_id`';
        $this->clients = full_query( $clients_query );
    }

    protected function getServers() {
        $servers_query = 'SELECT
                id,
                ipaddress,
                username,
                password
            FROM
                tblservers
            WHERE
                type = "onappusers"';
        $servers_result = full_query( $servers_query );
        while( $server = mysql_fetch_assoc( $servers_result ) ) {
            $server[ 'password' ] = decrypt( $server[ 'password' ] );
            $this->servers[ $server[ 'id' ] ] = $server;
        }
    }

    protected function generateInvoiceData( $data, array $client ) {
        global $_LANG;
        if( ! isset( $_LANG[ 'onappusersstatvirtualmachines' ] ) ) {
            eval( file_get_contents( dirname( __DIR__ ) . '/lang/English.txt' ) );
        }

        //check if invoice should be generated
        $fromTime = strtotime( $this->fromDate );
        $tillTime = strtotime( $this->tillDate );
        if( $fromTime >= $tillTime ) {
            return false;
        }

        if( $client[ 'dueDate' ] == 1 ) {
            $dueDate = $this->dueDate;
        }
        else {
            $dueDate = date( 'Ymd', ( time() + $GLOBALS[ 'CONFIG' ][ 'CreateInvoiceDaysBefore' ] * 86400 ) );
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

        $timeZone = ' UTC' . ( $this->timeZoneOffset >= 0 ? '+' : '-' ) . ( $this->timeZoneOffset / 3600 );

        $this->fromDate = date( $_LANG[ 'onappusersstatdateformat' ], $fromTime );
        $this->tillDate = date( $_LANG[ 'onappusersstatdateformat' ], $tillTime );
        $invoiceDescription = array(
            $_LANG[ 'onappusersstatproduct' ] . $client[ 'packagename' ],
            $_LANG[ 'onappusersstatperiod' ] . $this->fromDate . ' - ' . $this->tillDate . $timeZone,
        );
        $invoiceDescription = implode( PHP_EOL, $invoiceDescription );

        $return = array(
            'userid'           => $client[ 'client_id' ],
            'date'             => $this->dueDate,
            'duedate'          => $dueDate,
            'paymentmethod'    => $client[ 'paymentmethod' ],
            'taxrate'          => $taxrate,
            'sendinvoice'      => true,

            'itemdescription1' => $invoiceDescription,
            'itemamount1'      => 0,
            'itemtaxed1'       => $taxed,
        );

        $i = 1;

        if( $data->vm > 0 ) {
            $tmp = array(
                'itemdescription' . ++$i => $_LANG[ 'onappusersstatvirtualmachines' ],
                'itemamount' . $i        => $data->vm,
                'itemtaxed' . $i         => $taxed,
            );
            $return = array_merge( $return, $tmp );
        }

        if( $data->storage > 0 ) {
            $tmp = array(
                'itemdescription' . ++$i => $_LANG[ 'onappusersstatstoragediskssize' ],
                'itemamount' . $i        => $data->storage,
                'itemtaxed' . $i         => $taxed,
            );
            $return = array_merge( $return, $tmp );
            $data->resources -= $data->storage;
        }

        if( $data->resources > 0 ) {
            $tmp = array(
                'itemdescription' . ++$i => $_LANG[ 'onappusersstatotherresources' ],
                'itemamount' . $i        => $data->resources,
                'itemtaxed' . $i         => $taxed,
            );
            $return = array_merge( $return, $tmp );
        }

        return $return;
    }

    protected function getRequiredFiles() {
        global $whmcsmysql, $cc_encryption_hash, $templates_compiledir, $CONFIG, $_LANG, $whmcs;

        if( file_exists( $this->root . 'init.php' ) ) {
            require_once $this->root . 'init.php';
        }
        else {
            require_once $this->root . 'dbconnect.php';
            include_once $this->root . 'includes/functions.php';
        }

        require_once $this->root . '/modules/servers/onappusers/includes/php/SOP.php';

        error_reporting( E_ALL ^ E_NOTICE );
        ini_set( 'display_errors', 'On' );
    }

    private function checkSQL() {
        if( file_exists( $file = __DIR__ . '/cron.sql' ) ) {
            $sql = file_get_contents( $file );
            $sql = explode( PHP_EOL . PHP_EOL, $sql );

            foreach( $sql as $qry ) {
                full_query( $qry );
            }
            unlink( $file );
        }
    }

    private function checkCLIMode() {
        if( PHP_SAPI != 'cli' ) {
            if( ! empty( $_SERVER[ 'REMOTE_ADDR' ] ) ) {
                $this->log[ 'error' ] = 'Not allowed!';
                exit( 'Not allowed!' . PHP_EOL );
            }
        }
    }

    private function calculateDates() {
        $tmp = time();
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

        if( isset( $this->cliOptions->log ) ) {
            $this->log[ 'SQL from' ] = $this->fromDateUTC;
            $this->log[ 'SQL till' ] = $this->tillDateUTC;
        }

        $this->fromDate = $fromDate;
        $this->tillDate = $tillDate;
    }

    protected function getUTCTime( $date, $format = 'Y-m-d H:i' ) {
        return gmdate( $format, strtotime( $date ) - 3600 );
    }

    private function setCLIOptions() {
        $options = array(
            'since' => array(
                'description' => 'date to start',
                'validation'  => '^(20\d{2})-([0][1-9]|[1][0-2])-([\d]{2}) ([0-1][0-9]|[2][0-3]):([0-5][0-9])$',
                'short'       => 's',
            ),
            'till'  => array(
                'description' => 'date to finish',
                'validation'  => '^(20\d{2})-([0][1-9]|[1][0-2])-([\d]{2}) ([0-1][0-9]|[2][0-3]):([0-5][0-9])$',
                'short'       => 't',
            ),
            'log'   => array(
                'description' => 'log data to file',
                'short'       => 'l',
            ),
            'print' => array(
                'description' => 'print data to screen',
                'short'       => 'p',
            ),
        );

        $options = new SOP( $options );
        $options->setBanner( 'OnApp User Module detailed statistics and invoices processor' );
        $this->cliOptions = $options->parse();

        if( isset( $this->cliOptions->log ) ) {
            $this->logEnabled = true;
        }
        if( isset( $this->cliOptions->print ) ) {
            $this->printEnabled = true;
        }
    }

    private function writeLog() {
        $c = get_called_class();
        $logFile = __DIR__ . '/logs/' . $c::TYPE . '/' . date( 'Y-m-d-H-i-s' );

        if( ! ( file_exists( $logFile ) && is_writable( $logFile ) ) ) {
            if( ! $log = @fopen( $logFile, 'w' ) ) {
                exit( 'Can\'t write log file. Check if file ' . $logFile . ' exists and is writable!' . PHP_EOL );
            }
            else {
                fclose( $log );
            }
        }

        $log = print_r( $this->log, true );

        file_put_contents( $logFile, $log );
        echo PHP_EOL, ' Log has been saved in ' . $logFile, PHP_EOL;
    }

    public function __destruct() {
        if( $this->logEnabled ) {
            $this->writeLog();
        }
    }
}