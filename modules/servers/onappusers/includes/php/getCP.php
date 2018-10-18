<?php

if (!isset($_POST['authenticity_token'])) {
    exit('Don\'t allowed!');
}

$root = __DIR__ . '/../../../../../';
if (file_exists($root . 'init.php')) {
    require_once $root . 'init.php';
} else {
    require_once $root . 'dbconnect.php';
    require_once $root . 'includes/functions.php';
    require_once $root . 'includes/clientareafunctions.php';
}

$key = substr($_SESSION['utk'][0], 0, 32);
$encryptedData = base64_decode(base64_decode($_SESSION['utk'][1]));
if (function_exists('mcrypt_get_iv_size')) {
    $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
    $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
    $crypttext = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $encryptedData, MCRYPT_MODE_ECB, $iv);
} else {
    $ivLen = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($encryptedData, 0, $ivLen);
    $encryptedData = substr($encryptedData, $ivLen);
    $crypttext = openssl_decrypt($encryptedData, 'aes-256-cbc', $key, 0, $iv);
}

$data = explode('%%%', $crypttext);

if (count($data) != 2) {
    exit('Corrupted data!');
} else {
    $data = json_decode($data[0]);
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
    $curl->addOption(CURLOPT_RETURNTRANSFER, true);
    $curl->addOption(CURLOPT_FOLLOWLOCATION, true);
    $cp = $curl->get($data->server);

    if ($curl->getRequestInfo('http_code') == 200) {
        $js = <<<JS
            // fill data
            document.getElementById( 'user_login' ).value = '{$data->login}';
            document.getElementById( 'user_password' ).value = '{$data->password}';
            // add attributes
            document.getElementById( 'new_user' ).setAttribute( 'autocomplete', 'off' );
            document.getElementById( 'new_user' ).setAttribute( 'action', '{$data->server}/users/sign_in' );
            document.getElementById( 'user_password' ).setAttribute( 'type', 'hidden' );
            // submit form
            document.getElementById( 'new_user' ).submit();
            document.getElementById( 'new_user' ).outerHTML = '';
            document.getElementById( 'getcp' ).innerHTML = '';
            document.getElementById( 'cpform' ).style.display = 'block';
JS;
        $js = '<script type="text/javascript" id="getcp">' . $js . '</script>';
    } else {
        $js = '<script type="text/javascript">document.getElementById( "cpform" ).style.display = "block";</script><h1>Error!</h1>';
    }
    echo $cp . $js;
    ?>
</div>