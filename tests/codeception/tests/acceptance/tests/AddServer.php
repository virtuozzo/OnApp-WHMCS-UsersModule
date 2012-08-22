<?php

$I->amOnPage( '/admin/configservers.php' );
$I->dontSee( $server[ 'onappusers' ][ 'name' ] );
$I->amOnPage( '/admin/configservers.php?action=manage' );
$I->see( 'Add Server', 'h2' );

// add server
$I->fillField( 'name', $server[ 'onappusers' ][ 'name' ] );
$I->fillField( 'ipaddress', $server[ 'onappusers' ][ 'host' ] );
$I->fillField( 'username', $server[ 'onappusers' ][ 'user' ] );
$I->fillField( 'password', $server[ 'onappusers' ][ 'pass' ] );
$I->selectOption( 'type', $server[ 'onappusers' ][ 'type' ] );
$I->click( 'Save Changes' );
$I->see( 'Server Added Successfully!' );

// create server group
$I->amOnPage( '/admin/configservers.php?action=managegroup' );
$I->see( 'Create New Group', 'h2' );
$I->fillField( 'name', $server[ 'onappusers' ][ 'name' ] );
$I->selectOption( 'serverslist', $server[ 'onappusers' ][ 'name' ] );
$I->click( 'Add »' );
$I->click( 'Save Changes' );
$I->see( 'Servers', 'h1' );