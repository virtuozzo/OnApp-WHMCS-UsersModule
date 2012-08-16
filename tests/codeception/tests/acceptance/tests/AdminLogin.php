<?php

// login
$I->amOnPage( '/admin/login.php' );
$I->fillField( 'username', $WHMCS[ 'admin' ][ 'user' ] );
$I->fillField( 'password', $WHMCS[ 'admin' ][ 'pass' ] );
$I->click( 'Login' );
$I->see( 'Admin Summary' );