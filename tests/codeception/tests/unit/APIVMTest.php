<?php

use Codeception\Util\Stub;

class APIVMTest extends \Codeception\TestCase\Test {
	/**
	 * @var CodeGuy
	 */
	protected $codeGuy;

	private static $dataStorage;
	private $templatesIDs = array();
	private $hypervisorsIDs = array();
	private $dataStoresIDs = array();
	private $networksIDs = array();
	private $freeNetworksIDs = array();

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
	public function testCreateVM() {
		$this->getTempateIds();
		$this->getHypervisorsIDs();
		$this->getDataStores();
		$this->getNetworks();

		$vm = $this->getObject( 'OnApp_VirtualMachine' );

		$vm->label                          = 'testvm' . time();
		$vm->hostname                       = 'test.com';
		$vm->primary_network_id             = ( ! empty( $this->freeNetworksIDs ) ) ? $this->freeNetworksIDs[ 0 ] : $this->networksIDs[ 0 ];
		$vm->required_ip_address_assignment = 1;
		$vm->memory                         = 256;
		$vm->cpus                           = 1;
		$vm->cpu_shares                     = 1;
		$vm->swap_disk_size                 = 1;
		$vm->required_virtual_machine_build = false;
		$vm->allowed_hot_migrate            = false;
		$vm->required_automatic_backup      = false;
		$vm->template_id                    = $this->templatesIDs[ 0 ];
		$vm->initial_root_password          = 'testpwd';
		$vm->primary_disk_size              = 5;

		$vm->save();
		$this->assertEmpty( $vm->getErrorsAsArray(), 'There are errors while creating VM' );

		$repeat_times = round( 60 / 5 );
		for( $i = 0; ( $vm->loadedObject->locked == 'true' && $i < $repeat_times ); $i ++ ) {
			sleep( 5 );
			$vm->load();
		}
		self::$dataStorage[ 'vm_object' ] = $vm;
	}

	public function testBuildVM() {
		$this->markTestSkipped();

		$vm = self::$dataStorage[ 'vm_object' ];
		$vm->build();
		$msg = '';
		if( ! empty( $vm->loadedObject->errors ) ) {
			$msg = implode( PHP_EOL, $vm->loadedObject->errors );
		}
		else {
			$i = 1;
			while( ( $vm->loadedObject->built != true ) || ( $i > 30 ) ) {
				sleep( 5 );
				$vm->load();
				++$i;
			}
		}
		$this->assertTrue( $vm->loadedObject->built, $msg );
	}

	public function testEditVM() {
		$this->markTestSkipped();

		$vm        = self::$dataStorage[ 'vm_object' ];
		$vm->label = 'Edited';
		$vm->save();
		$this->assertEmpty( $vm->getErrorsAsArray(), 'There are errors while editing VM' );
	}

	public function testAddNetworkInterfaceToVM() {
		$this->markTestSkipped();

		$vm               = self::$dataStorage[ 'vm_object' ];
		$networkInterface = $this->getObject( 'OnApp_VirtualMachine_NetworkInterface' );
		$networkJoin      = $this->getObject( 'OnApp_Hypervisor_NetworkJoin' );
		$networkJoinList  = $networkJoin->getList( $vm->inheritedObject->hypervisor_id );
		if( empty( $networkJoinList ) ) {
			$this->fail( 'There are no networks assigned to this Hypervisor ( id = ' . $vm->hypervisor_id . ' )' );
		}

		$networkInterface->virtual_machine_id = $vm->loadedObject->id;
		$networkInterface->label              = 'test' . time();
		$networkInterface->network_join_id    = $networkJoinList[ 0 ]->id;

		$networkInterface->save();
		mail( 'devman@localhost', __METHOD__ . ' | ' . __LINE__, print_r( $networkInterface, 1 ) );
		$this->assertEmpty( $networkInterface->getErrorsAsArray(), 'There are errors while adding network interface' );
		self::$dataStorage[ 'networkInterface_object' ] = $networkInterface;
	}

	public function testAddIP() {
		$vm               = self::$dataStorage[ 'vm_object' ];
		$networkInterface = $this->getObject( 'OnApp_VirtualMachine_NetworkInterface' );
		$networkJoin      = $this->getObject( 'OnApp_Hypervisor_NetworkJoin' );
		$networkJoinList  = $networkJoin->getList( $vm->loadedObject->hypervisor_id );
		if( empty( $networkJoinList ) ) {
			$this->fail( 'There are no networks assigned to this Hypervisor ( id = ' . $vm->loadedObject->hypervisor_id . ' )' );
		}
		$networkInterfaceList = $networkInterface->getList( $vm->loadedObject->id );

		if( empty( $networkInterfaceList ) ) {
			$this->fail( 'VM has no Network Interfaces' );
		}
		$freeIPID           = 0;
		$networkInterfaceID = 0;
		foreach( $networkInterfaceList as $networkInterfaceItem ) {
			foreach( $networkJoinList as $networkJoinItem ) {
				if( $networkInterfaceItem->network_join_id == $networkJoinItem->id ) {
					$free_ips = $this->getFreeIPs( $networkJoinItem->network_id );
					if( ! empty( $free_ips ) ) {
						$freeIPID           = $free_ips[ 0 ];
						$networkInterfaceID = $networkInterfaceItem->id;
						break;
					}
				}
			}
		}
		if( ! $freeIPID ) {
			$this->fail( 'There are no free IPs' );
		}
		$ip                       = $this->getObject( 'OnApp_VirtualMachine_IpAddressJoin' );
		$ip->virtual_machine_id   = $vm->id;
		$ip->network_interface_id = $networkInterfaceID;
		$ip->ip_address_id        = $freeIPID;
		$ip->save();
		$this->assertEmpty( $ip->getErrorsAsArray(), 'There are errors while adding IP' );
	}

	private function getFreeIPs( $network_id ) {
		$ip         = $this->getObject( 'OnApp_IpAddress' );
		$IPsList    = $ip->getList( $network_id );
		$freeIPsIDs = array();

		if( ! is_null( $IPsList ) ) {
			foreach( $IPsList as $item ) {
				if( $item->free == true ) {
					$freeIPsIDs[ ] = $item->id;
				}
			}
		}

		return $freeIPsIDs;
	}

	private function getNetworks() {
		$network      = $this->getObject( 'OnApp_Network' );
		$networksList = $network->getList();
		foreach( $networksList as $item ) {
			$this->networksIDs[ ] = $item->id;
			$free_ips             = $this->getFreeIPs( $item->id );
			if( count( $free_ips ) ) {
				$this->freeNetworksIDs[ ] = $item->id;
			}
		}
	}

	private function getDataStores() {
		$dataStore      = $this->getObject( 'OnApp_DataStore' );
		$dataStoresList = $dataStore->getList();
		foreach( $dataStoresList as $item ) {
			$this->dataStoresIDs[ ] = $item->id;
		}
	}

	private function getHypervisorsIDs() {
		$hypervisor      = $this->getObject( 'OnApp_Hypervisor' );
		$hypervisorsList = $hypervisor->getList();
		foreach( $hypervisorsList as $item ) {
			$this->hypervisorsIDs[ ] = $item->id;
		}
	}

	private function getTempateIds() {
		$template      = $this->getObject( 'OnApp_Template' );
		$templatesList = $template->getList();
		$os            = 'linux';
		$os_distro     = '';

		foreach( $templatesList as $item ) {
			if( $os_distro ) {
				if( $item->operating_system_distro == $os_distro ) {
					$this->templatesIDs[ ] = $item->id;
				}
			}
			else {
				if( $item->operating_system == $os ) {
					$this->templatesIDs[ ] = $item->id;
				}
			}
		}
	}

	private function getObject( $class ) {
		$obj = new $class;
		$obj->auth( self::$dataStorage[ 'host' ], self::$dataStorage[ 'user' ], self::$dataStorage[ 'pass' ] );
		$this->assertTrue( empty( $obj->getErrorsAsArray ), 'Authorization failed' );
		return $obj;
	}
}