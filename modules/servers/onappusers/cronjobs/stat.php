<?php

require __DIR__ . '/common.php';

class OnApp_UserModule_Cron_Statistic extends OnApp_UserModule_Cron {
    const TYPE = 'statcollector';

    protected function run() {
        $this->getAdditionalFiles();
        $this->getStat();
    }

    private function getStat() {
        while( $client = mysql_fetch_assoc( $this->clients ) ) {
            if( isset( $this->cliOptions->since ) ) {
                $startDate = $this->getUTCTime( $this->cliOptions->since );
            }
            else {
                //get last stat retrieving date
                $qry = 'SELECT
                            MAX( `date` )
                        FROM
                            `onapp_itemized_resources`
                        WHERE
                            `whmcs_user_id` = :WHMCSUserID
                            AND `onapp_user_id` = :OnAppUserID
                            AND `server_id` = :serverID';
                $qry = str_replace( ':WHMCSUserID', $client[ 'client_id' ], $qry );
                $qry = str_replace( ':OnAppUserID', $client[ 'onapp_user_id' ], $qry );
                $qry = str_replace( ':serverID', $client[ 'server_id' ], $qry );

                if( $this->logEnabled ) {
                    $this->log[ ] = $qry;
                }

                $startDate = mysql_query( $qry );
                $startDate = mysql_result( $startDate, 0 );

                if( $startDate === null ) {
                    $startDate = $this->getUTCTime( date( 'Y-m-01 00:00' ) );
                }
            }

            if( isset( $this->cliOptions->till ) ) {
                $endDate = $this->getUTCTime( $this->cliOptions->till );
            }
            else {
                $endDate = gmdate( 'Y-m-d H:i' );
            }

            $date = array(
                'period[startdate]' => $startDate,
                'period[enddate]'   => $endDate,
            );

            if( $this->logEnabled ) {
                $this->log[ 'since' ] = $startDate;
                $this->log[ 'till' ] = $endDate;
            }
            if( $this->printEnabled ) {
                echo PHP_EOL, ' since: ', $startDate, PHP_EOL;
                echo ' till : ', $endDate, PHP_EOL;
            }

            $sql = $this->getVMStat( $client, $date );
            $this->processSQL( $sql );

            $sql = $this->getResourcesStat( $client, $date );
            $this->processSQL( $sql );
        }

        echo PHP_EOL, ' Itemized statistics cronjob finished successfully', PHP_EOL;
        echo ' Get data since ', $startDate, ' till ', $endDate;
        echo ' UTC', PHP_EOL;
    }

    private function getVMStat( $client, $date ) {
        $data = $this->getVMData( $client, $date );
        // process data
        $sql = array();
        foreach( $data as $stat ) {
            if( isset( $stat[ 'vm_hourly_stat' ] ) ) {
                $token = 'vm_hourly_stat';
            }
            elseif( isset( $stat[ 'vm_stats' ] ) ) {
                $token = 'vm_stats';
            }
            else {
                if( $this->logEnabled ) {
                    $this->log[ 'error' ] = 'Unknown token in server response';
                }
                exit( 'Unknown token in server response' . PHP_EOL );
            }

            $tmp = array();
            $tmp[ 'server_id' ] = $client[ 'server_id' ];
            $tmp[ 'whmcs_user_id' ] = $client[ 'client_id' ];
            $tmp[ 'id' ] = $stat[ $token ][ 'id' ];
            $tmp[ 'onapp_user_id' ] = $stat[ $token ][ 'user_id' ];
            $tmp[ 'date' ] = $stat[ $token ][ 'created_at' ];
            $tmp[ 'usage_cost' ] = $stat[ $token ][ 'usage_cost' ];
            $tmp[ 'total_cost' ] = $stat[ $token ][ 'total_cost' ];
            $tmp[ 'currency' ] = $stat[ $token ][ 'currency_code' ];
            $tmp[ 'vm_resources_cost' ] = $stat[ $token ][ 'vm_resources_cost' ];
            $tmp[ 'vm_id' ] = $stat[ $token ][ 'virtual_machine_id' ];

            foreach( $stat[ $token ][ 'billing_stats' ] as $name => $V ) {
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
            'server_id'     => $client[ 'server_id' ],
            'whmcs_user_id' => $client[ 'client_id' ],
            'onapp_user_id' => $client[ 'onapp_user_id' ],
        );

        $start = strtotime( $date[ 'period[startdate]' ] );
        $finish = strtotime( $date[ 'period[enddate]' ] ) - 3600;

        while( $start < $finish ) {
            $date = array(
                'period[startdate]' => date( 'Y-m-d H:10', $start ),
                'period[enddate]'   => date( 'Y-m-d H:10', $start += 3600 ),
            );

            $data = $this->getResourcesData( $client, $date )->user_stat;

            if( $data->stat_time === null ) {
                $statDate = $date[ 'period[enddate]' ];
            }
            else {
                $statDate = $data->stat_time;
            }

            unset( $data->vm_stats, $data->stat_time, $data->currency_code, $data->user_id );
            $data = (array)$data;
            $data = array_merge( $tmp, $data );
            $data[ 'date' ] = $statDate;

            $cols = implode( '`, `', array_keys( $data ) );
            $values = implode( '", "', array_values( $data ) );
            $sql_tmp = 'INSERT INTO `onapp_itemized_resources` ( `' . $cols . '` ) VALUES ( "' . $values . '" )';

            $sql[ ] = $sql_tmp;
        }

        return $sql;
    }

    private function getResourcesData( $client, $date ) {
        $date = http_build_query( $date );

        $url = $this->servers[ $client[ 'server_id' ] ][ 'ipaddress' ] . '/users/' . $client[ 'onapp_user_id' ] . '/user_statistics.json?' . $date;
        $data = $this->sendRequest( $url, $this->servers[ $client[ 'server_id' ] ][ 'username' ], $this->servers[ $client[ 'server_id' ] ][ 'password' ] );

        if( $data ) {
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
        if( $this->printEnabled ) {
            echo $url, PHP_EOL;
        }
        if( $this->logEnabled ) {
            $this->log[ ] = $url;
        }

        $curl = new CURL();
        $curl->addOption( CURLOPT_USERPWD, $user . ':' . $password );
        $curl->addOption( CURLOPT_HTTPHEADER, array( 'Accept: application/json', 'Content-type: application/json' ) );
        $curl->addOption( CURLOPT_HEADER, true );
        $data = $curl->get( $url );

        if( $curl->getRequestInfo( 'http_code' ) != 200 ) {
            $e = 'ERROR: ' . PHP_EOL;
            $e .= "\trequest URL:\t\t" . $url . PHP_EOL;
            $e .= "\trequest response:\t" . $curl->getRequestInfo( 'response_body' ) . PHP_EOL;
            if( $this->printEnabled ) {
                echo $e;
            }
            if( $this->logEnabled ) {
                $this->log[ ] = $e;
            }

            return false;
        }
        else {
            return $data;
        }
    }

    private function processSQL( array $sql ) {
        foreach( $sql as $record ) {
            $record .= ' ON DUPLICATE KEY UPDATE id = id';
            full_query( $record );
        }
    }

    private function getAdditionalFiles() {
        require_once dirname( __DIR__ ) . '/includes/php/CURL.php';
    }
}

new OnApp_UserModule_Cron_Statistic;