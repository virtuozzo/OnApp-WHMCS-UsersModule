<?php

if( defined( 'ROOTDIR' ) ) {
	require_once ROOTDIR . '/modules/servers/OnAppUsers/OnAppUsersHooks.php';
}
else {
	require_once dirname( dirname( __DIR__ ) ) . '/modules/servers/OnAppUsers/OnAppUsersHooks.php';
}