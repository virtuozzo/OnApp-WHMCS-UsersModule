<?php

session_start();

if( !isset( $_POST[ 'authenticity_token' ] ) ) {
	exit( 'Don\'t allowed!' );
}

$iv_size   = mcrypt_get_iv_size( MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB );
$iv        = mcrypt_create_iv( $iv_size, MCRYPT_RAND );
$key       = substr( $_SESSION[ 'utk' ][ 0 ], 0, 27 );
$crypttext = mcrypt_decrypt( MCRYPT_RIJNDAEL_256, $key, base64_decode( base64_decode( $_SESSION[ 'utk' ][ 1 ] ) ), MCRYPT_MODE_ECB, $iv );
$data      = explode( '%%%', $crypttext );

if( count( $data ) != 2 ) {
	exit( 'Corrupted data!' );
}
else {
	$data = json_decode( $data[ 0 ] );
}

?>

<noscript>
	<meta http-equiv="refresh" content="0; url=<?php echo $data->server ?>">
</noscript>
<base href="<?php echo $data->server ?>">
<div id="cpform" style="display: none;">
<?php

	require 'CURL.php';
	$curl = new CURL;
	$curl->addOption( CURLOPT_RETURNTRANSFER, true );
	$curl->addOption( CURLOPT_FOLLOWLOCATION, true );
	$cp = $curl->get( $data->server );

	if( $curl->getRequestInfo( 'http_code' ) == 200 ) {
		$js = <<<JS
			$( '#session_login' ).val( '{$data->login}' );
			$( '#session_password' ).val( '{$data->password}' );
			var form = $( 'form' );
			form.attr( 'action', '{$data->server}/session' );
			form.submit();
			$( '#getcp' ).remove();
JS;
		$js = '<script type="text/javascript" id="getcp">' . $js . '</script>';
	}
	else {
		$js = '<script type="text/javascript">document.getElementById( "cpform" ).style.display = "block";</script>';
	}
	echo $cp . $js;

?>
</div>
