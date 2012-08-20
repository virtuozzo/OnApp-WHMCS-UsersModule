<?php
namespace Codeception\Module;

// here you can define custom functions for WebGuy

class WebHelper extends \Codeception\Module {
	protected $storage = array();

	public function getServerID() {
		include realpath( __DIR__ . '/../acceptance/_bootstrap.php' );
		preg_match( '#<a .*href=".*&id=(.*)">' . $server[ 'onappusers' ][ 'name' ] . '#', $this->getPageSource(), $matches );
		$this->assertTrue( isset( $matches[ 1 ] ), 'Can\'t get server id' );
		return $matches[ 1 ];
	}

	public function getServerGroupID() {
		include realpath( __DIR__ . '/../acceptance/_bootstrap.php' );
		preg_match( '#<td>' . $server[ 'onappusers' ][ 'name' ] . '</td>.*doDeleteGroup\(\'(.*)\'\)#', $this->getPageSource(), $matches );
		$this->assertTrue( isset( $matches[ 1 ] ), 'Can\'t get server group id' );
		return $matches[ 1 ];
	}

	public function getProductID() {
		include realpath( __DIR__ . '/../acceptance/_bootstrap.php' );
		preg_match( '#<td>' . $server[ 'onappusers' ][ 'name' ] . '</td>.*doDelete\(\'(.*)\'\)#', $this->getPageSource(), $matches );
		$this->assertTrue( isset( $matches[ 1 ] ), 'Can\'t get product id' );
		return $matches[ 1 ];
	}

	public function getProductGroupID() {
		include realpath( __DIR__ . '/../acceptance/_bootstrap.php' );
		preg_match( '#' . $server[ 'onappusers' ][ 'name' ] . '.*doGroupDelete\(\'(.*)\'\)#', $this->getPageSource(), $matches );
		$this->assertTrue( isset( $matches[ 1 ] ), 'Can\'t get product group id' );
		return $matches[ 1 ];
	}

	public function getTokenFromPage() {
		preg_match( '#&token=(.*)\';#', $this->getPageSource(), $matches );
		$this->assertTrue( isset( $matches[ 1 ] ), 'Can\'t get security token' );
		return $matches[ 1 ];
	}

	public function cleanUPStore() {
		include_once \Codeception\Configuration::config()[ 'project directory' ] . 'configuration.php';

		mysql_connect( $db_host, $db_username, $db_password );
		mysql_select_db( $db_name );

		$qry = 'SELECT
					`tblonappusers`.`onapp_user_id`
				FROM
					tblonappusers
				JOIN
					`tblclients`
					ON tblclients.`id` = tblonappusers.`client_id`
				WHERE
					CONCAT_WS( " ", tblclients.`firstname`, tblclients.`lastname` ) = "Codeception User"';

		$res = mysql_query( $qry );
		while( $r = mysql_fetch_assoc( $res ) ) {
			$this->storage[] = $r[ 'onapp_user_id' ];
		}
	}

	public function doCleanUP() {
		if(! empty( $this->storage ) ) {
			include_once \Codeception\Configuration::config()[ 'project directory' ] . 'includes/wrapper/OnAppInit.php';
			include realpath( __DIR__ . '/../acceptance/_bootstrap.php' );


			$user = new \OnApp_User();
			$user->auth( $server[ 'onappusers' ][ 'IP' ], $server[ 'onappusers' ][ 'user' ], $server[ 'onappusers' ][ 'pass' ] );

			foreach( $this->storage as $id ) {
				$user->_id = $id;
				$user->delete();
			}
		}
	}

	private function getPageSource() {
		return html_entity_decode( $this->getModule( 'Selenium2' )->session->getPage()->getContent() );
	}
}
