<?php
@ini_set( 'memory_limit', '512M' );
@ini_set( 'max_execution_time', 0 );
@set_time_limit( 0 );

abstract class OnApp_UserModule_Cron {
	protected $root, $clients, $servers = array();

	abstract protected function run();

	public function __construct() {
		$this->checkCLIMode();
		$this->root = realpath( dirname( dirname( dirname( dirname( dirname( $_SERVER[ 'argv' ][ 0 ] ) ) ) ) ) ) . DIRECTORY_SEPARATOR;
		$this->getRequiredFiles();
		$this->checkSQL();
		$this->getServers();
		$this->getClients();
		$this->run();
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
			AND tblproducts.name IS NOT NULL';
		$this->clients = full_query( $clients_query );
	}

	protected function getServers() {
		$servers_query  = 'SELECT
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
			$server[ 'password' ]             = decrypt( $server[ 'password' ] );
			$this->servers[ $server[ 'id' ] ] = $server;
		}
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

		error_reporting( E_ALL ^ E_NOTICE );
		ini_set( 'display_errors', 'On' );
	}

	private function checkSQL() {
		if( file_exists( $file = dirname( __FILE__ ) . '/onapp.cron.sql' ) ) {
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
				exit( 'Not allowed!' );
			}
		}
	}

	protected function getUTCTime( $date, $format = 'Y-m-d H:i:s' ) {
		return gmdate( $format, strtotime( $date ) );
	}

	protected function validateDate( $date, $exit = true ) {
		$check = false;
		if( preg_match( "/^(20\d{2})-(\d{2})-([\d]{2}) ([0-1][0-9]|[2][0-3]):([0-5][0-9]):([0-5][0-9])$/", $date, $parts ) ) {
			if( checkdate( $parts[ 2 ], $parts[ 3 ], $parts[ 1 ] ) ) {
				$check = true;
			}
		}
		if( ! $check && $exit ) {
			exit( 'Not valid date!' . PHP_EOL );
		}
		else {
			return $check;
		}
	}
}