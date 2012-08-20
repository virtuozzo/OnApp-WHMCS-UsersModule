<?php

// create product group
$I->amOnPage( '/admin/configproducts.php' );
$I->dontSee( $server[ 'onappusers' ][ 'name' ] );
$I->amOnPage( '/admin/configproducts.php?action=creategroup' );
$I->see( 'Create Group' );
$I->fillField( 'name', $server[ 'onappusers' ][ 'name' ] );
$I->click( 'Create Group' );
$I->see( 'Products/Services', 'h1' );

// create product
$I->amOnPage( '/admin/configproducts.php?action=create' );
$I->see( 'Add New Product' );
$I->selectOption( 'type', 'other' );
$I->selectOption( 'gid', $server[ 'onappusers' ][ 'name' ] );
$I->fillField( 'productname', $server[ 'onappusers' ][ 'name' ] );
$I->click( 'Continue >>' );
$I->see( 'Edit Product' );
$I->click( 'Module Settings' );
$I->see( 'Module Name' );
$I->selectOption( 'servertype', $server[ 'onappusers' ][ 'type' ] );
$I->click( 'Save Changes' );
$I->see( 'Changes Saved Successfully!' );
$I->selectOption( 'servergroup', $server[ 'onappusers' ][ 'name' ] );
$I->click( 'Save Changes' );
$I->see( 'Changes Saved Successfully!' );

// configure product
$I->checkOption( "//text()[. = ' User']/preceding-sibling::input[1]" );
$I->selectOption( '//select[starts-with(@name, "tzs_packageconfigoption")]', 'Kyiv' );
$I->checkOption( '//input[starts-with(@name, "stat_packageconfigoption")]' );
$I->checkOption( '//input[starts-with(@name, "controlpanel_packageconfigoption")]' );

// propagate jquery events
$js = <<<JS
	$( "input[name^='controlpanel_packageconfigoption']" ).change();
	$( "input[name^='stat_packageconfigoption']" ).change();
	$( "select[name^='tzs_packageconfigoption']" ).change();
	$( "input[name^='roles_packageconfigoption']" ).change();
JS;
$I->executeJs( $js );

$I->click( 'Save Changes' );
$I->see( 'Changes Saved Successfully!' );