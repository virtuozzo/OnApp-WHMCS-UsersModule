<?php

$I->amOnPage( '/admin/configservers.php' );
$I->see( 'Servers', 'h1' );

// delete server
$I->amOnPage( '/admin/configservers.php?action=delete&id=' . $I->getServerID() . '&token=' . $token );
$I->see( 'Server Deleted Successfully!' );

// delete group
$I->amOnPage( '/admin/configservers.php?action=deletegroup&id=' . $I->getServerGroupID() . '&token=' . $token );
$I->see( 'Server Group Deleted Successfully!' );