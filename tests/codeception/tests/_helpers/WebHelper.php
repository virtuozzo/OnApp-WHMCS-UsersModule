<?php
namespace Codeception\Module;

// here you can define custom functions for WebGuy
// todo add check for matches in methods

class WebHelper extends \Codeception\Module {
	public function getServerID() {
		include realpath( __DIR__ . '/../acceptance/_bootstrap.php' );
		preg_match( '#<a .*href=".*&id=(.*)">' . $server[ 'onappusers' ][ 'name' ] . '#', $this->getPageSource(), $matches );
		return $matches[ 1 ];
	}

	public function getServerGroupID() {
		include realpath( __DIR__ . '/../acceptance/_bootstrap.php' );
		preg_match( '#<td>' . $server[ 'onappusers' ][ 'name' ] . '</td>.*doDeleteGroup\(\'(.*)\'\)#', $this->getPageSource(), $matches );
		return $matches[ 1 ];
	}

	public function getTokenFromPage() {
		preg_match( '#&token=(.*)\';#', $this->getPageSource(), $matches );
		return $matches[ 1 ];
	}

	private function getPageSource() {
		return html_entity_decode( $this->getModule( 'Selenium2' )->session->getPage()->getContent() );
	}
}
