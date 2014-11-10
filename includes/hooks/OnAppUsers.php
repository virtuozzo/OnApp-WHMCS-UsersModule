<?php

if( defined( 'ROOTDIR' ) ) {
	require_once ROOTDIR . '/modules/servers/OnAppUsers/moduleHooks.php';
}
else {
	require_once dirname( dirname( __DIR__ ) ) . '/modules/servers/OnAppUsers/moduleHooks.php';
}