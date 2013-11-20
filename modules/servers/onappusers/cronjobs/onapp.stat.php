<?php

require dirname( __FILE__ ) . '/onapp.cron.php';
class OnApp_UserModule_Cron_Statistic extends OnApp_UserModule_Cron {
	protected function run() {
		$this->getAdditionalFiles();
		$this->getStat();
	}

	private function getStat() {
		$endDate = gmdate( 'Y-m-d H:i:00' );

		while( $client = mysql_fetch_assoc( $this->clients ) ) {
			//get last stat retrieving date
			$qry = 'SELECT
						`Date`
					FROM
						`onapp_itemized_last_check`
					WHERE
						`WHMCSUserID` = :WHMCSUserID';
			$qry = str_replace( ':WHMCSUserID', $client[ 'client_id' ], $qry );

			if( isset( $_SERVER[ 'argv' ][ 1 ] ) && $this->validateDate( $_SERVER[ 'argv' ][ 1 ] ) ) {
				$startDate = $_SERVER[ 'argv' ][ 1 ];
			}
			else {
				$startDate = mysql_query( $qry );
				if( ( $startDate === false ) || ( mysql_num_rows( $startDate ) == 0 ) ) {
					$startDate = gmdate( 'Y-m-01 00:00:00' );
				}
				else {
					$startDate = mysql_result( $startDate, 0 );
					$startDate = date( 'Y-m-d H:00:00', strtotime( $startDate ) - ( 2 * 3600 ) );
					$startDate = substr_replace( $startDate, '00', - 2 );
				}
			}

			$date = array(
				'period[startdate]' => $startDate,
				'period[enddate]'   => $endDate,
			);

			$sql = $this->getVMStat( $client, $date );
			$sql = array_merge( $sql, $this->getResourcesStat( $client, $date ) );

			// process SQL
			foreach( $sql as $record ) {
				$record .= ' ON DUPLICATE KEY UPDATE id = id';
				full_query( $record );
			}
		}

		echo 'Itemized cron job finished successfully', PHP_EOL;
		echo 'Get data from ', $startDate, ' to ', $endDate;
		echo ' (UTC)', PHP_EOL;
	}

	private function getVMStat( $client, $date ) {
		$data = $this->getVMData( $client, $date );
		// process data
		$sql = array();
		foreach( $data as $stat ) {
			$tmp = array();
			$tmp[ 'server_id' ] = $client[ 'server_id' ];
			$tmp[ 'whmcs_user_id' ] = $client[ 'client_id' ];
			$tmp[ 'date' ] = $stat[ 'vm_stats' ][ 'created_at' ];
			$tmp[ 'id' ] = $stat[ 'vm_stats' ][ 'id' ];
			$tmp[ 'usage_cost' ] = $stat[ 'vm_stats' ][ 'usage_cost' ];
			$tmp[ 'total_cost' ] = $stat[ 'vm_stats' ][ 'total_cost' ];
			$tmp[ 'onapp_user_id' ] = $stat[ 'vm_stats' ][ 'user_id' ];
			$tmp[ 'currency' ] = $stat[ 'vm_stats' ][ 'currency_code' ];
			$tmp[ 'vm_id' ] = $stat[ 'vm_stats' ][ 'virtual_machine_id' ];
			$tmp[ 'vm_resources_cost' ] = $stat[ 'vm_stats' ][ 'vm_resources_cost' ];

			foreach( $stat[ 'vm_stats' ][ 'billing_stats' ] as $name => $V ) {
				if( ( $name == 'virtual_machines' ) ) {
					if( is_null( $tmp[ 'vm_id' ] ) ) {
						$tmp[ 'vm_id' ] = $V[ 0 ][ 'id' ];
					}
				}

				foreach( $V as $value ) {
					$sql_tmp = 'INSERT INTO `onapp_itemized_' . $name . '` SET stat_id = ' . $tmp[ 'id' ] . ', id = ' . $value[ 'id' ];
					foreach( $value[ 'costs' ] as $v ) {
						$sql_tmp .= ', ' . $v[ 'resource_name' ] . ' = ' . $v[ 'value' ];
						$sql_tmp .= ', ' . $v[ 'resource_name' ] . '_cost = ' . $v[ 'cost' ];
					}
					$sql_tmp .= ', label = "' . $value[ 'label' ] . '"';
					$sql[ ] = $sql_tmp;
				}
			}

			$cols = implode( ', ', array_keys( $tmp ) );
			$values = implode( '", "', array_values( $tmp ) );
			$sql_tmp = 'INSERT INTO `onapp_itemized_stat` ( ' . $cols . ' ) VALUES ( "' . $values . '" )';
			$sql[ ] = $sql_tmp;
		}
		return $sql;
	}

	private function getResourcesStat( $client, $date ) {
		$sql = array();
		$tmp = array(
			'server_id' => $client[ 'server_id' ],
			'whmcs_user_id' => $client[ 'client_id' ],
			'onapp_user_id' => $client[ 'onapp_user_id' ],
		);
		$start = strtotime( $date[ 'period[startdate]' ] );
		$finish = strtotime( $date[ 'period[enddate]' ] );

		while( $start < $finish ) {
			$date = array(
				'period[startdate]' => date( 'Y-m-d H:10:s', $start ),
				'period[enddate]'   => date( 'Y-m-d H:10:s', $start += 3600 ),
			);

			$data = $this->getResourcesData( $client, $date )->user_stat;

			if( $data->total_cost == 0 ) {
				continue;
			}
			unset( $data->vm_stats, $data->stat_time, $data->currency_code, $data->user_id );
			$data = (array)$data;
			$data = array_merge( $tmp, $data );
			$data[ 'date' ] = $date[ 'period[enddate]' ];

			$cols = implode( '`, `', array_keys( $data ) );
			$values = implode( '", "', array_values( $data ) );
			$sql_tmp = 'INSERT INTO `onapp_itemized_resources` ( `' . $cols . '` ) VALUES ( "' . $values . '" )';
			$sql[] = $sql_tmp;
		}
		return $sql;
	}

	private function getResourcesData( $client, $date ) {
		$dateAsArray = $date;
		$date = http_build_query( $date );

		$url = $this->servers[ $client[ 'server_id' ] ][ 'ipaddress' ] . '/users/' . $client[ 'onapp_user_id' ] . '/user_statistics.json?' . $date;
		$data = $this->sendRequest( $url, $this->servers[ $client[ 'server_id' ] ][ 'username' ], $this->servers[ $client[ 'server_id' ] ][ 'password' ] );

		if( $data ) {
			$this->saveLastCheckDate( $client, $dateAsArray );
			return json_decode( $data );
		}
		else {
			return array();
		}
	}

	private function getVMData( $client, $date ) {
		$date = http_build_query( $date );

		$url = $this->servers[ $client[ 'server_id' ] ][ 'ipaddress' ] . '/users/' . $client[ 'onapp_user_id' ] . '/vm_stats.json?' . $date;
		$data = $this->sendRequest( $url, $this->servers[ $client[ 'server_id' ] ][ 'username' ], $this->servers[ $client[ 'server_id' ] ][ 'password' ] );

		if( $data ) {
			return json_decode( $data, true );
		}
		else {
			return array();
		}
	}

	private function sendRequest( $url, $user, $password ) {
		$curl = new CURL();
		$curl->addOption( CURLOPT_USERPWD, $user . ':' . $password );
		$curl->addOption( CURLOPT_HTTPHEADER, array( 'Accept: application/json', 'Content-type: application/json' ) );
		$curl->addOption( CURLOPT_HEADER, true );
		$data = $curl->get( $url );

		if( $curl->getRequestInfo( 'http_code' ) != 200 ) {
			echo 'ERROR: ', PHP_EOL;
			echo "\trequest URL:\t\t", $url, PHP_EOL;
			echo "\trequest response:\t", $curl->getRequestInfo( 'response_body' ), PHP_EOL;
			return false;
		}
		else {
			return $data;
		}
	}

	private function saveLastCheckDate( $client, $date ) {
		$qry = 'INSERT INTO
					`onapp_itemized_last_check`
				VALUES
					(
						:serverID,
						:WHMCSUserID,
						:OnAppUserID,
						""
					)
				ON DUPLICATE KEY UPDATE
					`Date` = ":Date"';
		$qry = str_replace( ':serverID', $client[ 'server_id' ], $qry );
		$qry = str_replace( ':WHMCSUserID', $client[ 'client_id' ], $qry );
		$qry = str_replace( ':OnAppUserID', $client[ 'onapp_user_id' ], $qry );
		$qry = str_replace( ':Date', $date[ 'period[enddate]' ], $qry );
		full_query( $qry );
	}

	private function getAdditionalFiles() {
		require_once dirname( dirname( __FILE__ ) ) . '/includes/php/CURL.php';
	}
}
new OnApp_UserModule_Cron_Statistic;