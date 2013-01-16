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
		$this->expectedException = false;
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
		$user->role_ids = array( 2 );
		$user->user_group_id = $this->getUserGroup();
		$user->save();
		$this->assertEquals( $user->loadedObject->status, 'active' );
		self::$dataStorage[ 'user_object' ] = $user;
	}

	public function testEditUser() {
		$this->assertNotNull( self::$dataStorage[ 'user_object' ], 'Can\'t get active user' );
		$user = self::$dataStorage[ 'user_object' ];
		$rand = 'Edit';
		$user->first_name .= $rand;
		$user->last_name .= $rand;
		unset( $user->login, $user->loadedObject->login );
		$user->save();
		$this->assertFalse( $user->isError(), $user->getErrorsAsString() );
	}

	public function testSuspendUser() {
		$this->assertNotNull( self::$dataStorage[ 'user_object' ], 'Can\'t get active user' );
		$user = self::$dataStorage[ 'user_object' ];
		$user->suspend();
		$this->assertEquals( $user->loadedObject->status, 'suspended' );
		$this->assertFalse( $user->isError(), $user->getErrorsAsString() );
	}

	public function testUnsuspendUser() {
		$this->assertNotNull( self::$dataStorage[ 'user_object' ], 'Can\'t get active user' );
		$user = self::$dataStorage[ 'user_object' ];
		$user->activate_user();
		$this->assertEquals( $user->loadedObject->status, 'active' );
		$this->assertFalse( $user->isError(), $user->getErrorsAsString() );
	}

	public function testDeleteUser() {
		$this->assertNotNull( self::$dataStorage[ 'user_object' ], 'Can\'t get active user' );
		$user = self::$dataStorage[ 'user_object' ];
		$user->delete( true );
		$this->assertFalse( $user->isError(), $user->getErrorsAsString() );
	}

	private function getUserGroup() {
		$groups = $this->getObject( 'OnApp_UserGroup' )->getList();
		$this->assertFalse( empty( $groups ), 'Can\'t get user groups' );
		return $groups[ 0 ]->id;
	}

	/**
	 * @param $class
	 *
	 * @return OnApp_User
	 */
	private function getObject( $class ) {
		$obj = new $class;
		$obj->auth( self::$dataStorage[ 'host' ], self::$dataStorage[ 'user' ], self::$dataStorage[ 'pass' ] );
		$obj->getOnAppVersion();
		$this->assertFalse( $obj->isError(), $obj->getErrorsAsString() );
		return $obj;
	}
}