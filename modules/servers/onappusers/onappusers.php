<?php

if( ! function_exists( 'onappusers_ConfigOptions' ) ) {
	if( ! defined( 'ONAPP_WRAPPER_INIT' ) ) {
		define( 'ONAPP_WRAPPER_INIT', ROOTDIR . '/includes/wrapper/OnAppInit.php' );
	}

	if( file_exists( ONAPP_WRAPPER_INIT ) ) {
		require_once ONAPP_WRAPPER_INIT;
	}

	function onappusers_ConfigOptions() {
		global $_LANG;

		if( ! file_exists( ONAPP_WRAPPER_INIT ) ) {
			$configarray = array(
				$_LANG[ 'onappwrappernotfound' ] . realpath( ROOTDIR ) . '/includes' => array()
			);
			return $configarray;
		}

		// Should return an array of the module options for each product - maximum of 24
		if( ( $res = create_table() ) !== true ) {
			$configarray = array(
				'<b style="color:red;">' . $res . '</b>' => array()
			);
		}
		else {
			$js = '<script type="text/javascript" src="../modules/servers/onappusers/includes/js/onappusers.js"></script>';
			$js .= '<script type="text/javascript" src="../modules/servers/onappusers/includes/js/tz.js"></script>';
			$js .= '<script type="text/javascript" src="../modules/servers/onappusers/includes/js/jquery.json-2.2.min.js"></script>';

			$servergroup = isset( $_GET[ 'servergroup' ] ) ? $_GET[ 'servergroup' ] : (int)$GLOBALS[ 'servergroup' ];
			$sql = 'SELECT srv.`id`, srv.`name`, srv.`ipaddress`, srv.`hostname`, srv.`username`, srv.`password`'
					. ' FROM `tblservers` AS srv'
					. ' LEFT JOIN `tblservergroupsrel` AS rel ON srv.`id` = rel.`serverid`'
					. ' LEFT JOIN `tblservergroups` AS grp ON grp.`id` = rel.`groupid` WHERE grp.`id` = ' . $servergroup;

			$res = full_query( $sql );

			$js_Servers = '';
			if( mysql_num_rows( $res ) == 0 ) {
				$js_Servers = 'NoServers:"' . addslashes( sprintf( $_LANG[ 'onappuserserrorholder' ], $_LANG[ 'onappuserserrornoserveringroup' ] ) ) . '",';
			}
			else {
				while( $onapp_config = mysql_fetch_assoc( $res ) ) {
					$onapp_config[ 'password' ] = decrypt( $onapp_config[ 'password' ] );
					$onapp_config[ 'adress' ] = $onapp_config[ 'ipaddress' ] != '' ?
							$onapp_config[ 'ipaddress' ] : $onapp_config[ 'hostname' ];

					//Error if server adress (IP and hostname) not set
					if( empty( $onapp_config[ 'adress' ] ) ) {
						$msg = sprintf( $_LANG[ 'onapperrcantfoundadress' ] );

						$js_Servers .= $onapp_config[ 'id' ] . ':{Name:"' . $onapp_config[ 'name' ] . '", BillingPlans:"'
								. addslashes( sprintf( $_LANG[ 'onappuserserrorholder' ], $msg ) ) . '"},';

						continue;
					}

					// handle billing plans/groups per server
					$groups = getPlans( $onapp_config );
					if( empty( $groups ) ) {
						$msg = sprintf( $_LANG[ 'onappusersnobillingplans' ] );

						$billing_plans = '"' . addslashes( sprintf( $_LANG[ 'onappuserserrorholder' ], $msg ) ) . '"';
					}
					else {
						$tmp_BillingPlans = '';
						foreach( $groups as $group ) {
							$tmp_BillingPlans .= $group->_id . ':"' . addslashes( $group->_label ) . '",';
						}
						$billing_plans = '{' . substr( $tmp_BillingPlans, 0, - 1 ) . '}';
					}

					//handle user roles per server
					$roles = getRoles( $onapp_config );
					if( empty( $roles ) ) {
						$msg = sprintf( $_LANG[ 'onappusersnoroles' ] );
						$roles = '"' . addslashes( sprintf( $_LANG[ 'onappuserserrorholder' ], $msg ) ) . '"';
					}
					else {
						$tmp_Roles = '';
						foreach( $roles as $role ) {
							$tmp_Roles .= $role->_id . ':"' . addslashes( $role->_label ) . '",';
						}
						$roles = '{' . substr( $tmp_Roles, 0, - 1 ) . '}';
					}

					// handle user groups per server
					$usergroups = getUsersGroups( $onapp_config );
					if( empty( $usergroups ) ) {
						$msg = sprintf( $_LANG[ 'onappusersnousergroups' ] );

						$usergroups = '"' . addslashes( sprintf( $_LANG[ 'onappuserserrorholder' ], $msg ) ) . '"';
					}
					else {
						$tmp_UserGroups = '';
						foreach( $usergroups as $group ) {
							$tmp_UserGroups .= $group->_id . ':"' . addslashes( $group->_label ) . '",';
						}
						$usergroups = '{' . substr( $tmp_UserGroups, 0, - 1 ) . '}';
					}

					$js_Servers .= $onapp_config[ 'id' ] . ':{Name:"' . $onapp_config[ 'name' ] . '", BillingPlans:'
							. $billing_plans . ', Roles:' . $roles . ', UserGroups:' . $usergroups . '},';
				}
			}

			$sql = 'SELECT prod.`configoption1` AS options, prod.`servergroup` AS `group`'
					. ' FROM `tblproducts` AS prod WHERE prod.`id` = ' . (int)$_GET[ 'id' ];
			$results = full_query( $sql );
			$results = mysql_fetch_assoc( $results );
			$results[ 'options' ] = htmlspecialchars_decode( $results[ 'options' ] );
			$results[ 'options' ] = substr( $results[ 'options' ], 1, -1 );
			$results[ 'options' ] = $results[ 'options' ] ? $results[ 'options' ] . ',' : '';

			$js_Servers = '{' . $js_Servers . $results[ 'options' ] . 'Group:"' . $results[ 'group' ] . '"}';

			$js_lang = getJSLang();
			$js .= '<script type="text/javascript">'
					. 'var ServersData = ' . $js_Servers . ';'
					. 'var ONAPP_LANG = ' . getJSLang() . ';'
					. '</script>';

			if( isset( $_GET[ 'servergroup' ] ) ) {
				ob_end_clean();
				exit( $js_Servers );
			}

			$configarray = array(
				sprintf( '' )  => array(
					'Description' => $js
				),
			);
		}

		return $configarray;
	}

	function onappusers_CreateAccount( $params ) {
		global $CONFIG, $_LANG;

		if( ! file_exists( ONAPP_WRAPPER_INIT ) ) {
			return $_LANG[ 'onappwrappernotfound' ] . realpath( ROOTDIR ) . '/includes';
		}

		$serviceid = $params[ 'serviceid' ]; // Unique ID of the product/service in the WHMCS Database
		$pid = $params[ 'pid' ]; // Product/Service ID
		$producttype = $params[ 'producttype' ]; // Product Type: hostingaccount, reselleraccount, server or other
		$domain = $params[ 'domain' ];
		$username = $params[ 'username' ];
		$password = $params[ 'password' ];
		$clientsdetails = $params[ 'clientsdetails' ]; // Array of clients details - firstname, lastname, email, country, etc...
		$customfields = $params[ 'customfields' ]; // Array of custom field values for the product
		$configoptions = $params[ 'configoptions' ]; // Array of configurable option values for the product

		// Additional variables if the product/service is linked to a server
		$server = $params[ 'server' ]; // True if linked to a server
		$server_id = $params[ 'serverid' ];
		$server_ip = empty( $params[ 'serverip' ] ) ? $params[ 'serverhostname' ] : $params[ 'serverip' ];
		$server_username = $params[ 'serverusername' ];
		$server_password = $params[ 'serverpassword' ];

		// Save hosting username
		if( ! $username ) {
			$username = $clientsdetails[ 'email' ];
			full_query( "UPDATE
                tblhosting
            SET
                username = '$username'
            WHERE
                id = '$serviceid'" );
		}

		if( ! $password ) {
			return $_LANG[ 'onappuserserrusercreate' ] . "<br/>\n" . $_LANG[ 'onappuserserrpwdnotset' ];
		}

		/*********************************/
		/*      Create OnApp user        */
		$onapp_user = get_onapp_object( 'OnApp_User', $server_ip, $server_username, $server_password );

		$onapp_user->logger->setDebug( 1 );

		//$onapp_user->_email = $serviceid . '_' . $clientsdetails[ 'email' ];
		$onapp_user->_email = $clientsdetails[ 'email' ];
		$onapp_user->_password = $onapp_user->_password_confirmation = $password;
		$onapp_user->_password = $password;
		$onapp_user->_login = $username;
		$onapp_user->_first_name = $clientsdetails[ 'firstname' ];
		$onapp_user->_last_name = $clientsdetails[ 'lastname' ];

		$params[ 'configoption1' ] = html_entity_decode( $params[ 'configoption1' ] );
		$params[ 'configoption1' ] = json_decode( $params[ 'configoption1' ], true );
		// Assign billing group/plan to user
		$group_id = $params[ 'configoption1' ][ 'SelectedPlans' ][ $params[ 'serverid' ] ];

		if( $onapp_user->_version > 2 ) {
			$onapp_user->_billing_plan_id = $group_id;
		}
		else {
			$onapp_user->_group_id = $group_id;
		}

		$tmp = array();
		$onapp_user->_role_ids = array_merge( $tmp, $params[ 'configoption1' ][ 'SelectedRoles' ][ $params[ 'serverid' ] ] );

		// Assign TZ to user
		$onapp_user->_time_zone = $params[ 'configoption1' ][ 'SelectedTZs' ][ $params[ 'serverid' ] ];

		// Assign user group to user
		$onapp_user->_user_group_id = $params[ 'configoption1' ][ 'SelectedUserGroups' ][ $params[ 'serverid' ] ];

		// Assign locale to user
		if( isset( $params[ 'configoption1' ][ 'SelectedLocales' ][ $params[ 'serverid' ] ] ) ) {
			$onapp_user->_locale = $params[ 'configoption1' ][ 'SelectedLocales' ][ $params[ 'serverid' ] ];
		}

		$onapp_user->save();
		if( ! is_null( $onapp_user->getErrorsAsArray() ) ) {
			$error_msg = $_LANG[ 'onappuserserrusercreate' ] . ':<br/>';
			$error_msg .= $onapp_user->getErrorsAsString( '<br/>' );
			return $error_msg;
		}

		if( ! is_null( $onapp_user->_obj->getErrorsAsArray() ) ) {
			$error_msg = $_LANG[ 'onappuserserrusercreate' ] . ':<br/>';
			$error_msg .= $onapp_user->_obj->getErrorsAsString( '<br/>' );
			return $error_msg;
		}

		if( is_null( $onapp_user->_obj->_id ) ) {
			return $_LANG[ 'onappuserserrusercreate' ];
		}
		/*    End Create OnApp user      */
		/*********************************/

		// Save user data in whmcs db
		$res_insert = insert_query( 'tblonappusers', array(
														  'server_id'	 => $server_id,
														  'client_id'	 => $clientsdetails[ 'userid' ],
														  'service_id'	=> $serviceid,
														  'onapp_user_id' => $onapp_user->_obj->_id,
														  'password'	  => $password,
														  'email'		 => $clientsdetails[ 'email' ]
													 ) );

		sendmessage( $_LANG[ 'onappuserscreateaccount' ], $serviceid );

		return 'success';
	}

	function onappusers_TerminateAccount( $params ) {
		global $_LANG;

		if( ! file_exists( ONAPP_WRAPPER_INIT ) ) {
			return $_LANG[ 'onappwrappernotfound' ] . realpath( ROOTDIR ) . '/includes';
		}

		$serviceid = $params[ 'serviceid' ];
		$client_id = $params[ 'clientsdetails' ][ 'userid' ];
		$server_id = $params[ 'serverid' ];
		$server_ip = empty( $params[ 'serverip' ] ) ? $params[ 'serverhostname' ] : $params[ 'serverip' ];

		$server_username = $params[ 'serverusername' ];
		$server_password = $params[ 'serverpassword' ];

		$query = "SELECT
            onapp_user_id
        FROM
            tblonappusers
        WHERE
            server_id = $server_id
            AND client_id = $client_id
            AND service_id = $serviceid";

		$result = full_query( $query );
		if( $result ) {
			$onapp_user_id = mysql_result( $result, 0 );
		}
		if( ! $onapp_user_id ) {
			return sprintf( $_LANG[ 'onappuserserrassociateuser' ], $client_id, $server_id );
		}

		$onapp_user = get_onapp_object( 'OnApp_User', $server_ip, $server_username, $server_password );

		$vms = get_onapp_object( 'OnApp_VirtualMachine', $server_ip, $server_username, $server_password );
		if( $vms->getList( $onapp_user_id ) ) {
			$error_msg = $_LANG[ 'onappuserserruserterminate' ];
			return $error_msg;
		}

		$onapp_user->_id = $onapp_user_id;
		$onapp_user->delete();
		$onapp_user->delete();

		if( ! is_null( $onapp_user->error ) ) {
			$error_msg = $_LANG[ 'onappuserserrusercreate' ] . ':<br/>';
			$error_msg .= $onapp_user->getErrorsAsString( '<br/>' );
			return $error_msg;
		}
		else {
			$query = 'DELETE FROM tblonappusers WHERE service_id = ' . (int)$serviceid
					. ' AND client_id = ' . (int)$client_id . ' AND server_id = ' . (int)$server_id;
			full_query( $query );
		}

		return 'success';
	}

	function onappusers_SuspendAccount( $params ) {
		global $_LANG;

		if( ! file_exists( ONAPP_WRAPPER_INIT ) ) {
			return $_LANG[ 'onappwrappernotfound' ] . realpath( ROOTDIR ) . '/includes';
		}

		$serviceid = $params[ 'serviceid' ];
		$client_id = $params[ 'clientsdetails' ][ 'userid' ];
		$server_id = $params[ 'serverid' ];
		$server_ip = empty( $params[ 'serverip' ] ) ? $params[ 'serverhostname' ] : $params[ 'serverip' ];
		$server_username = $params[ 'serverusername' ];
		$server_password = $params[ 'serverpassword' ];

		$query = "SELECT
            onapp_user_id
        FROM
            tblonappusers
        WHERE
            server_id = $server_id
            AND client_id = $client_id
            AND service_id = $serviceid";

		$result = full_query( $query );
		if( $result ) {
			$onapp_user_id = mysql_result( $result, 0 );
		}
		if( ! $onapp_user_id ) {
			return sprintf( $_LANG[ 'onappuserserrassociateuser' ], $client_id, $server_id );
		}

		$onapp_user = get_onapp_object( 'OnApp_User', $server_ip, $server_username, $server_password );
		$onapp_user->logger->setDebug( 1 );

		$onapp_user->_id = $onapp_user_id;

		$onapp_user->suspend();

		if( ! is_null( $onapp_user->error ) ) {
			$error_msg = $_LANG[ 'onappuserserrusercreate' ] . ':<br/>';
			$error_msg .= $onapp_user->getErrorsAsString( '<br/>' );
			return $error_msg;
		}

		return 'success';
	}

	function onappusers_UnsuspendAccount( $params ) {
		global $_LANG;

		if( ! file_exists( ONAPP_WRAPPER_INIT ) ) {
			return $_LANG[ 'onappwrappernotfound' ] . realpath( ROOTDIR ) . '/includes';
		}

		$serviceid = $params[ 'serviceid' ];
		$client_id = $params[ 'clientsdetails' ][ 'userid' ];
		$server_id = $params[ 'serverid' ];
		$server_ip = empty( $params[ 'serverip' ] ) ? $params[ 'serverhostname' ] : $params[ 'serverip' ];
		$server_username = $params[ 'serverusername' ];
		$server_password = $params[ 'serverpassword' ];

		$query = "SELECT
            onapp_user_id
        FROM
            tblonappusers
        WHERE
            server_id = '$server_id'
            AND client_id = '$client_id'
            AND service_id = '$serviceid'";

		$result = full_query( $query );
		if( $result ) {
			$onapp_user_id = mysql_result( $result, 0 );
		}
		if( ! $onapp_user_id ) {
			return sprintf( $_LANG[ 'onappuserserrassociateuser' ], $client_id, $server_id );
		}

		$onapp_user = get_onapp_object( 'OnApp_User', $server_ip, $server_username, $server_password );

		$onapp_user->_id = $onapp_user_id;

		$onapp_user->activate_user();

		if( ! is_null( $onapp_user->error ) ) {
			$error_msg = $_LANG[ 'onappuserserrusercreate' ] . ':<br/>';
			$error_msg .= $onapp_user->getErrorsAsString( '<br/>' );
			return $error_msg;
		}

		return 'success';
	}

	/**
	 * Create table for user data
	 *
	 * @return mixed Return true on success, Otherwise error string.
	 */
	function create_table() {
		global $_LANG;

		$query = 'CREATE TABLE IF NOT EXISTS `tblonappusers` (
            `server_id` int( 11 ) NOT NULL,
            `client_id` int( 11 ) NOT NULL,
            `service_id` int( 11 ) NOT NULL,
            `onapp_user_id` int( 11 ) NOT NULL,
            `password` text NOT NULL,
            `email` text NOT NULL,
            PRIMARY KEY( `server_id`, `client_id`, `service_id` ),
            KEY `client_id` ( `client_id` )
        ) ENGINE = InnoDB;';
		if( ! full_query( $query ) ) {
			return sprintf( $_LANG[ 'onappuserserrtablecreate' ], 'onappclients' );
		}

		$col_exist_res = full_query( 'DESCRIBE tblonappusers service_id' );
		if( ! mysql_num_rows( $col_exist_res ) ) {
			$alter_query = 'ALTER TABLE tblonappusers
            ADD service_id int( 11 ) NOT NULL';
			full_query( $alter_query );

			if( mysql_num_rows( full_query( "SHOW KEYS FROM tblonappusers WHERE Key_name = 'PRIMARY'" ) ) ) {
				full_query( 'ALTER TABLE tblonappusers DROP PRIMARY KEY' );
			}

			$services_query = 'SELECT
                tblonappusers . server_id, tblonappusers . client_id, tblhosting . id
            FROM
                tblonappusers, tblhosting
            WHERE
                tblhosting . userid = tblonappusers . client_id
                AND tblhosting . server = tblonappusers . server_id';

			$services_res = full_query( $services_query );

			while( $service = mysql_fetch_assoc( $services_res ) ) {
				$where = array(
					'server_id' => $service[ 'server_id' ],
					'client_id' => $service[ 'client_id' ]
				);
				update_query( 'tblonappusers', array( 'service_id' => $service[ 'id' ] ), $where );
			}

			full_query( 'ALTER TABLE tblonappusers ADD PRIMARY KEY( server_id, client_id, service_id )' );
		}

		// Add e-mail templates
		$where = array();
		$where[ 'type' ] = 'product';
		$where[ 'name' ] = $_LANG[ 'onappuserscreateaccount' ];
		if( ! mysql_num_rows( select_query( 'tblemailtemplates', 'id', $where ) ) ) {
			$insert_fields = array();
			$insert_fields[ 'type' ] = 'product';
			$insert_fields[ 'name' ] = $_LANG[ 'onappuserscreateaccount' ];
			$insert_fields[ 'subject' ] = 'OnApp account has been created';
			$insert_fields[ 'message' ] = ' < p>Dear {$client_name}</p > ' .
					'<p > Your OnApp account has been created<br />' .
					'login: {$service_username}<br />' .
					'password: {$service_password}</p > ' .
					'<p ></p > To login, visit http://{$service_server_ip}';
			$insert_fields[ 'plaintext' ] = 0;
			if( ! insert_query( 'tblemailtemplates', $insert_fields ) ) {
				return sprintf( $_LANG[ 'onappuserserrtmpladd' ], $_LANG[ 'onappuserscreateaccount' ] );
			}
		}

		return true;
	}

	/**
	 * Get an instance of specified wrapper class
	 * and autorize it on OnApp server;
	 *
	 * @param $class	 string
	 * @param $server_ip string
	 * @param $email	 string
	 * @param $apikey	string
	 *
	 * @return object
	 */
	function get_onapp_object( $class, $server_ip, $username = null, $apikey = null ) {
		$obj = new $class;
		$obj->auth( $server_ip, $username, $apikey );

		return $obj;
	}

	/**
	 * Load $_LANG from language file
	 */
	function load_lang() {
		global $_LANG;
		$dh = opendir( dirname( __FILE__ ) . '/lang/' );

		while( false !== $file2 = readdir( $dh ) ) {
			if( ! is_dir( '' . 'lang/' . $file2 ) ) {
				$pieces = explode( '.', $file2 );
				if( $pieces[ 1 ] == 'txt' ) {
					$arrayoflanguagefiles[ ] = $pieces[ 0 ];
				}
			}
		}

		closedir( $dh );

		$language = @$_SESSION[ 'Language' ];

		if( ! in_array( $language, $arrayoflanguagefiles ) ) {
			$language = "English";
		}

		ob_start();
		include dirname( __FILE__ ) . "/lang/$language.txt";
		$templang = ob_get_contents();
		ob_end_clean();
		eval ( $templang );
		return $templang;
	}

	function getPlans( $params ) {
		$server_ip = empty( $params[ 'ipaddress' ] ) ? $params[ 'hostname' ] : $params[ 'ipaddress' ];
		$server_username = $params[ 'username' ];
		$server_password = $params[ 'password' ];

		$obj = get_onapp_object( 'OnApp_User', $server_ip, $server_username, $server_password );

		$class = ( (float)$obj->_version > 2 ) ? 'OnApp_BillingPlan' : 'OnApp_Group';
		$plans = get_onapp_object( $class, $server_ip, $server_username, $server_password );

		return $plans->getList();
	}

	function getRoles( $params ) {
		$server_ip = empty( $params[ 'ipaddress' ] ) ? $params[ 'hostname' ] : $params[ 'ipaddress' ];
		$server_username = $params[ 'username' ];
		$server_password = $params[ 'password' ];

		$roles = get_onapp_object( 'OnApp_Role', $server_ip, $server_username, $server_password );

		return $roles->getList();
	}

	function getUsersGroups( $params ) {
		$server_ip = empty( $params[ 'ipaddress' ] ) ? $params[ 'hostname' ] : $params[ 'ipaddress' ];
		$server_username = $params[ 'username' ];
		$server_password = $params[ 'password' ];

		$groups = get_onapp_object( 'OnApp_UserGroup', $server_ip, $server_username, $server_password );

		return $groups->getList();
	}

	function getJSLang() {
		global $_LANG;
		return json_encode( $_LANG );

		//todo del this stuff
		$_LANG = array();
		eval( load_lang() );
		return json_encode( $_LANG );

		//todo del this stuff
		$js = '';
		foreach( $_LANG as $key => $item ) {
			$js .= $key . ':' . '"' . addslashes( $item ) . '",';
		}

		return substr( $js, 0, - 1 );
	}

	function parseLang( &$html ) {
		$html = preg_replace_callback(
			'#{\$LANG.(.*)}#',
			create_function(
				'$matches',
				'global $_LANG; return $_LANG[ $matches[ 1 ] ];'
			),
			$html
		);
		return $html;
	}

	function onappusers_ClientArea( $params = '' ) {
		$sets = json_decode( htmlspecialchars_decode( $params[ 'configoption1' ] ), true );
		if( ! $sets[ 'ShowStat' ][ $params[ 'serverid' ] ] ) {
			return '';
		}

		if( isset( $_GET[ 'getstat' ] ) ) {
			onappusers_OutstandingDetails( $params );
		}

		$html = file_get_contents( dirname( __FILE__ ) . '/clientarea.html' );
		parseLang( $html );
		$html .= '<script type="text/javascript">'
				. 'var UID = ' . $params[ 'clientsdetails' ][ 'userid' ] . ';'
				. 'var PID = ' . $params[ 'accountid' ] . ';'
				. 'var LANG = ' . getJSLang() . ';</script>';

		return $html;
	}

	function onappusers_OutstandingDetails( $params = '' ) {
		$limit = 10;
		$page = (int)$_GET[ 'page' ];
		$start = ( $page - 1 ) * $limit;
		if( $_GET[ 'tz_offset' ] != 0 ) {
			$date_start = date( 'Y-m-d H:00:00', strtotime( $_GET[ 'start' ] ) + ( $_GET[ 'tz_offset' ] * 60 ) );
			$date_end = date( 'Y-m-d H:00:00', strtotime( $_GET[ 'end' ] ) + ( $_GET[ 'tz_offset' ] * 60 ) );
		}
		else {
			$date_start = mysql_real_escape_string( $_GET[ 'start' ] );
			$date_end = mysql_real_escape_string( $_GET[ 'end' ] );
		}

		$sql = 'SELECT
					`server_id`,
					`client_id` AS whmcs_user_id,
					`onapp_user_id`
				FROM
					`tblonappusers`
				WHERE
					`service_id` = ' . $params[ 'serviceid' ] . '
				LIMIT 1';

		$user = mysql_fetch_assoc( full_query( $sql ) );

		$sql = 'SELECT
					SQL_CALC_FOUND_ROWS
					-- `stat`.`date` AS ORIGINAL_DATE,
					ADDTIME( `stat`.`date`, SEC_TO_TIME( -( ' . $_GET[ 'tz_offset' ] . ' ) * 60 ) ) AS `date`,
					`stat`.`currency`,
					`stat`.`vm_resources_cost`,
					`stat`.`usage_cost`,
					`stat`.`total_cost`,
					vm.*
				FROM
					`onapp_itemized_stat` AS stat
				JOIN `onapp_itemized_virtual_machines` AS vm
					ON vm.`stat_id` = stat.`id`
				WHERE
					`whmcs_user_id` = ' . $user[ 'whmcs_user_id' ] . '
					AND `onapp_user_id` = ' . $user[ 'onapp_user_id' ] . '
					AND `server_id` = ' . $params[ 'serverid' ] . '
					AND `date` BETWEEN "' . $date_start . '"
					AND ADDTIME( "' . $date_end . '", "00:10" )
				ORDER BY `date` DESC
				LIMIT ' . $start . ', ' . $limit;
		$res = full_query( $sql );

		$total = mysql_result( mysql_query( 'SELECT FOUND_ROWS()' ), 0 );

		$stat = array();
		while( $row = mysql_fetch_assoc( $res ) ) {
			$tmp = array();

			//get disks
			$sql = 'SELECT * FROM `onapp_itemized_disks` WHERE `stat_id` = ' . $row[ 'stat_id' ];
			$res_tmp = full_query( $sql );
			while( $item = mysql_fetch_assoc( $res_tmp ) ) {
				$tmp[ 'stat' ][ 'disks' ][ ] = $item;
			}

			//get networks
			$sql = 'SELECT * FROM `onapp_itemized_network_interfaces` WHERE `stat_id` = ' . $row[ 'stat_id' ];
			$res_tmp = full_query( $sql );
			while( $item = mysql_fetch_assoc( $res_tmp ) ) {
				$tmp[ 'stat' ][ 'nets' ][ ] = $item;
			}

			$stat[ ] = array_merge( $row, $tmp );
		}

		//get total amount
		$total_amount = 0;
		//get total amount for vm
		$sql = 'SELECT
					(
					SUM( vm.`cpus_cost` ) +
					SUM( vm.`cpu_shares_cost` ) +
					SUM( vm.`memory_cost` ) +
					SUM( vm.`template_cost` ) +
					SUM( vm.`cpu_usage_cost` )
					) AS total
				FROM
					`onapp_itemized_stat` AS stat
				JOIN `onapp_itemized_virtual_machines` AS vm
					ON vm.`stat_id` = stat.`id`
				WHERE
					`whmcs_user_id` = ' . $user[ 'whmcs_user_id' ] . '
					AND `onapp_user_id` = ' . $user[ 'onapp_user_id' ] . '
					AND `server_id` = ' . $params[ 'serverid' ] . '
					AND `date` BETWEEN "' . $_GET[ 'start' ] . '"
					AND "' . $_GET[ 'end' ] . '"';
		$total_amount += mysql_result( mysql_query( $sql ), 0 );
		//get total amount for disks
		$sql = 'SELECT
					(
					SUM( dsk.`disk_size_cost` ) +
					SUM( dsk.`data_read_cost` ) +
					SUM( dsk.`data_written_cost` ) +
					SUM( dsk.`reads_completed_cost` ) +
					SUM( dsk.`writes_completed_cost` )
					) AS total
				FROM
					`onapp_itemized_stat` AS stat
				JOIN `onapp_itemized_disks` AS dsk
					ON dsk.`stat_id` = stat.`id`
				WHERE
					`whmcs_user_id` = ' . $user[ 'whmcs_user_id' ] . '
					AND `onapp_user_id` = ' . $user[ 'onapp_user_id' ] . '
					AND `server_id` = ' . $params[ 'serverid' ] . '
					AND `date` BETWEEN "' . $_GET[ 'start' ] . '"
					AND "' . $_GET[ 'end' ] . '"';
		$total_amount += mysql_result( mysql_query( $sql ), 0 );
		//get total amount for nets
		$sql = 'SELECT
					(
					SUM( net.`ip_addresses_cost` ) +
					SUM( net.`rate_cost` ) +
					SUM( net.`data_received_cost` ) +
					SUM( net.`data_sent_cost` )
					) AS total
				FROM
					`onapp_itemized_stat` AS stat
				JOIN `onapp_itemized_network_interfaces` AS net
					ON net.`stat_id` = stat.`id`
				WHERE
					`whmcs_user_id` = ' . $user[ 'whmcs_user_id' ] . '
					AND `onapp_user_id` = ' . $user[ 'onapp_user_id' ] . '
					AND `server_id` = ' . $params[ 'serverid' ] . '
					AND `date` BETWEEN "' . $_GET[ 'start' ] . '"
					AND "' . $_GET[ 'end' ] . '"';
		$total_amount += mysql_result( mysql_query( $sql ), 0 );

		$data = array(
			'total'        => $total,
			'page'         => $page,
			'stat'         => $stat,
			'limit'        => $limit,
			'total_amount' => $total_amount,
		);
		$data = json_encode( $data );
		exit( $data );
	}

	load_lang();
}