<?php

// login
$I->amOnPage( '/admin/login.php' );
$I->fillField( 'username', 'admin' );
$I->fillField( 'password', 'admin' );
$I->click( 'Login' );
$I->see( 'Admin Summary' );