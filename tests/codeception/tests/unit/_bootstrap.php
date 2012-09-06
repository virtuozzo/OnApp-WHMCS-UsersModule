<?php
// Here you can initialize variables that will for your tests

include \Codeception\Configuration::config()[ 'paths' ][ 'tests' ] . '/acceptance/_bootstrap.php';

include_once \Codeception\Configuration::config()[ 'project directory' ] . 'includes/wrapper/OnAppInit.php';