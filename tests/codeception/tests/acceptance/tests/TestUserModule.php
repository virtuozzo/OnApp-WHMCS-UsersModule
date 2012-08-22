<?php

// make order
$I->amOnPage( '/admin/ordersadd.php' );
$I->selectOption( 'userid', 'Codeception User' );
$I->selectOption( 'paymentmethod', 'Bank Transfer' );
$I->selectOption( 'pid0', $server[ 'onappusers' ][ 'name' ] );
$I->fillField( 'domain0', $clientUID . '.codeception.test' );
$I->uncheckOption( 'adminorderconf' );
$I->uncheckOption( 'admingenerateinvoice' );
$I->uncheckOption( 'adminsendinvoice' );
$I->click( 'Submit Order' );
$I->see( 'Manage Orders' );
$I->click( 'Accept Order' );
$I->see( 'Order Accepted' );

// create service
$I->click( 'Product/Service' );
$I->wait( 1000 );
$I->see( 'Clients Profile' );
$I->click( "//input[@value='Create']" );
$I->click( "//div[11]/div[3]/div/button[1]" );
$I->see( 'Service Created Successfully' );

// suspend service
$I->click( "//input[@value='Suspend']" );
$I->click( "//div[12]/div[3]/div/button[1]" );
$I->see( 'Service Suspended Successfully' );

// unsuspend service
$I->click( "//input[@value='Unsuspend']" );
$I->click( "//div[13]/div[3]/div/button[1]" );
$I->see( 'Service Unsuspended Successfully' );

// terminate service
$I->click( "//input[@value='Terminate']" );
$I->click( "//div[14]/div[3]/div/button[1]" );
$I->see( 'Service Terminated Successfully' );

// grab order ID
$orederID = $I->grabTextFrom( '//div[5]/div[2]/div/form/table/tbody/tr[1]/td[2]' );
$orederID = (int)$orederID->__value();

// delete product
$I->click( "//b[text()='Delete']" );
$I->click( "//div[16]/div[3]/div/button[1]" );
$I->see( 'Clients Profile' );

// delete order
$orederID = (string)$orederID;
$I->amOnPage( '/admin/orders.php' );
$I->see( $orederID, 'a > b' );
$I->checkOption( "//input[@value='" . $orederID . "']" );
$I->click( "//input[@value='Delete Order']" );
$I->see( 'Manage Orders' );
$I->dontSee( $orederID, 'a > b' );