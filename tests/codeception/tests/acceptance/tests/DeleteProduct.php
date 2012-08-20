<?php

$I->amOnPage( '/admin/configproducts.php' );
$I->see( 'Products/Services', 'h1' );

$token = $I->getTokenFromPage();

// delete server
$I->amOnPage( '/admin/configproducts.php?sub=delete&id=' . $I->getProductID() . '&token=' . $token );
$I->see( 'Products/Services' );

// delete group
$I->amOnPage( '/admin/configproducts.php?sub=deletegroup&id=' . $I->getProductGroupID() . '&token=' . $token );
$I->see( 'Products/Services' );