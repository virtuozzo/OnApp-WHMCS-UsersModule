<?php

use Codeception\Util\Stub;

class APIUserTest extends \Codeception\TestCase\Test {
	/**
	 * @var CodeGuy
	 */
	protected $codeGuy;
	private static $dataStorage;

	// keep this setupUp and tearDown to enable proper work of Codeception modules
	protected function setUp() {
		if( $this->bootstrap && is_null( self::$dataStorage ) ) {
			require $this->bootstrap;
			self::$dataStorage = $server[ 'onappusers' ];
		}

		$this->dispatcher->dispatch( 'test.before', new \Codeception\Event\Test( $this ) );
		$this->codeGuy = new CodeGuy( $scenario = new \Codeception\Scenario( $this ) );
		$scenario->run();
		// initialization code
	}

	protected function tearDown() {
		$this->dispatcher->dispatch( 'test.after', new \Codeception\Event\Test( $this ) );
	}

	// tests
	public function testCreateUser() {
		$rand = uniqid();
		$user = $this->getObject( 'OnApp_User' );
		$user->email = $rand . 'codeception@test.com';
		$user->first_name = 'CodeceptionUser';
		$user->last_name = $rand;
		$user->login = $rand;
		$user->password = $rand;
		$user->locale = 'ru';
		$user->role_ids = array( 2 );
		$user->user_group_id = $this->getUserGroup();
		$user->save();
		$this->assertEquals( $user->inheritedObject->status, 'active' );
		self::$dataStorage[ 'user_object' ] = $user;
	}

	public function testEditUser() {
		$rand = 'Edit';
		$user = self::$dataStorage[ 'user_object' ];
		$user->first_name .= $rand;
		$user->last_name .= $rand;
		unset( $user->login, $user->inheritedObject->login );
		$user->save();
		$this->assertNull( $user->getErrorsAsArray() );
	}

	public function testSuspendUser() {
		$user = self::$dataStorage[ 'user_object' ];
		$user->suspend();
		$this->assertEquals( $user->inheritedObject->status, 'suspended' );
	}

	public function testUnsuspendUser() {
		$user = self::$dataStorage[ 'user_object' ];
		$user->activate_user();
		$this->assertEquals( $user->inheritedObject->status, 'active' );
	}

	public function testDeleteUser() {
		$user = self::$dataStorage[ 'user_object' ];
		$user->delete( TRUE );
		$this->assertNull( $user->getErrorsAsArray() );
	}

	private function getUserGroup() {
		$groups = $this->getObject( 'OnApp_UserGroup' )->getList();
		$this->assertFalse( empty( $groups ), 'Can\t get user groups' );
		return $groups[ 0 ]->id;
	}

	private function getObject( $class ) {
		$obj = new $class;
		$obj->auth( self::$dataStorage[ 'host' ], self::$dataStorage[ 'user' ], self::$dataStorage[ 'pass' ] );
		$this->assertTrue( $obj->_is_auth, 'Authorization failed' );
		return $obj;
	}
}