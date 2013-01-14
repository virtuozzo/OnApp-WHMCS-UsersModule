<?php
// Here you can initialize variables that will for your tests

$path = \Codeception\Configuration::projectDir() . \Codeception\Configuration::config()[ 'paths' ][ 'tests' ];

include $path . '/acceptance/_bootstrap.php';

include_once dirname( \Codeception\Configuration::projectDir() ) . '/includes/wrapper/OnAppInit.php';