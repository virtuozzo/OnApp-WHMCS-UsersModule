<?php
namespace Codeception\Module;

// here you can define custom functions for WebGuy

class WebHelper extends \Codeception\Module {
	protected $data;

	public function __construct() {
		$path = \Codeception\Configuration::config();
		$path = \Codeception\Configuration::projectDir() . $path[ 'paths' ][ 'tests' ];
		include_once $path . '/acceptance/_bootstrap.php';
		$this->data = $server[ 'onappusers' ];
	}

	public function getServerID() {
		preg_match( '#<a .*href=".*&id=(.*)">' . $this->data[ 'name' ] . '#', $this->getPageSource(), $matches );
		$this->assertTrue( isset( $matches[ 1 ] ), 'Can\'t get server id' );
		return $matches[ 1 ];
	}

	public function getServerGroupID() {
		preg_match( '#<td>' . $this->data[ 'name' ] . '</td>.*doDeleteGroup\(\'(.*)\'\)#', $this->getPageSource(), $matches );
		$this->assertTrue( isset( $matches[ 1 ] ), 'Can\'t get server group id' );
		return $matches[ 1 ];
	}

	public function getProductID() {
		preg_match( '#<td>' . $this->data[ 'name' ] . '</td>.*doDelete\(\'(.*)\'\)#', $this->getPageSource(), $matches );
		$this->assertTrue( isset( $matches[ 1 ] ), 'Can\'t get product id' );
		return $matches[ 1 ];
	}

	public function getProductGroupID() {
		preg_match( '#' . $this->data[ 'name' ] . '.*doGroupDelete\(\'(.*)\'\)#', $this->getPageSource(), $matches );
		$this->assertTrue( isset( $matches[ 1 ] ), 'Can\'t get product group id' );
		return $matches[ 1 ];
	}

	public function getTokenFromPage() {
		preg_match( '#&token=(.*)\';#', $this->getPageSource(), $matches );
		$this->assertTrue( isset( $matches[ 1 ] ), 'Can\'t get security token' );
		return $matches[ 1 ];
	}

	private function getPageSource() {
		return html_entity_decode( $this->getModule( 'Selenium2' )->session->getPage()->getContent() );
	}
}