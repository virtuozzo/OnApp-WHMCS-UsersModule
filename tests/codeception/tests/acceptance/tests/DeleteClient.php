<?php

$I->amOnPage( '/admin/clientssummary.php?userid=' . $clientID );
$I->see( 'Clients Profile', 'h1' );

$token = $I->grabValueFrom( "//input[@name='token']" )->__value();

$I->amOnPage( '/admin/clientssummary.php?userid=' . $clientID . '&action=deleteclient&token=' . $token );
$I->see( 'View/Search Clients' );