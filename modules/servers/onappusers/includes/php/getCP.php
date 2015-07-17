<?php

if( ! isset( $_POST[ 'authenticity_token' ] ) ) {
    exit( 'Don\'t allowed!' );
}

$root = dirname( dirname( dirname( dirname( dirname( dirname( $_SERVER[ 'SCRIPT_FILENAME' ] ) ) ) ) ) ) . DIRECTORY_SEPARATOR;
if( file_exists( $root . 'init.php' ) ) {
    require_once $root . 'init.php';
}
else {
    require_once $root . 'dbconnect.php';
    require_once $root . 'includes/functions.php';
    require_once $root . 'includes/clientareafunctions.php';
}

$iv_size = mcrypt_get_iv_size( MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB );
$iv = mcrypt_create_iv( $iv_size, MCRYPT_RAND );
$key = substr( $_SESSION[ 'utk' ][ 0 ], 0, 32 );
$crypttext = mcrypt_decrypt( MCRYPT_RIJNDAEL_256, $key, base64_decode( base64_decode( $_SESSION[ 'utk' ][ 1 ] ) ), MCRYPT_MODE_ECB, $iv );
$data = explode( '%%%', $crypttext );

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
            var jQueryScriptOutputted = false;
            function initJQuery() {
                //if the jQuery object isn't available
                if( typeof( jQuery ) == 'undefined' ) {
                    if( ! jQueryScriptOutputted ) {
                        jQueryScriptOutputted = true;
                        document.write( '<scr' + 'ipt type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.1.4/jquery.js"></scr' + 'ipt>' );
                    }
                    setTimeout( 'initJQuery()', 50 );
                } else {
                    $(function() {
                        $( '#user_login' ).val( '{$data->login}' );
                        $( '#user_password' ).remove();
                        $('<input>').attr( {
                            type: 'hidden',
                            id: 'user_password',
                            name: 'user[password]',
                            value: '{$data->password}'
                        }).appendTo( 'form' );
                        var form = $( 'form' );
                        form.attr( 'action', '{$data->server}/users/sign_in' );
                        form.attr( 'autocomplete', 'off' );
                        form.submit();
                        $( '#getcp' ).remove();
                    });
                }
            }
            initJQuery();
JS;
        $js = '<script type="text/javascript" id="getcp">' . $js . '</script>';
    }
    else {
        $js = '<script type="text/javascript">document.getElementById( "cpform" ).style.display = "block";</script><h1>Error!</h1>';
    }
    echo $cp . $js;
    ?>
</div>