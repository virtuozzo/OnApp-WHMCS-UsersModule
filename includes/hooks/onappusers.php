<?php
function autosuspend_onappusers() {
    require_once("../modules/servers/onappusers/onappusers.php");
    global $CONFIG;

    if ($CONFIG['AutoSuspension'] != 'on') {
        return;
    }

    $suspenddate = date ('Ymd', mktime (0, 0, 0, date ('m'), date ('d') - $CONFIG['AutoSuspensionDays'], date ('Y')));
    $to_suspend_query = "SELECT tblhosting.id
        FROM 
            tblinvoices
            LEFT JOIN tblinvoiceitems ON
                tblinvoiceitems.invoiceid = tblinvoices.id
            LEFT JOIN tblhosting ON
                tblhosting.id = tblinvoiceitems.relid
        WHERE
            tblinvoices.status = 'Unpaid'
            AND tblinvoiceitems.type = 'onappusers'
            AND tblhosting.domainstatus = 'Active'
            AND tblinvoices.duedate <= $suspenddate
            AND tblhosting.overideautosuspend != 'on'";
    $to_suspend_result = full_query($to_suspend_query);

    while ($to_suspend = mysql_fetch_assoc($to_suspend_result)){
        serversuspendaccount($to_suspend['id']);
    }
}

function unsuspend_user($vars) {
    require_once("../modules/servers/onappusers/onappusers.php");
    include ROOTDIR . '/includes/modulefunctions.php';

    $invoice_id = $vars['invoiceid'];
    $client_query = "SELECT
            tblonappusers.client_id,
            tblonappusers.server_id,
            tblonappusers.onapp_user_id,
            tblhosting.id AS service_id,
            SUM(tblinvoiceitems.amount) AS amount
        FROM
            tblinvoices
            LEFT JOIN tblinvoiceitems ON
                tblinvoiceitems.invoiceid = tblinvoices.id
            LEFT JOIN tblonappusers ON
                tblinvoiceitems.userid = tblonappusers.client_id
            LEFT JOIN tblhosting ON
                tblhosting.userid = tblonappusers.client_id
                AND tblhosting.server = tblonappusers.server_id
        WHERE
            tblinvoices.id = $invoice_id
            AND tblinvoiceitems.type = 'onappusers'
        GROUP BY tblinvoices.id";
    $client_result = full_query($client_query);
    $client = mysql_fetch_assoc($client_result);

    $servers_query = "SELECT
            id,
            ipaddress,
            username,
            password 
        FROM
            tblservers
        WHERE
            type = 'onappusers'";
    $servers_result = full_query($servers_query);
    $servers = array();
    while ($server = mysql_fetch_assoc($servers_result)) {
        $server['password'] = decrypt($server['password']);
        $servers[$server['id']] = $server;
    }
    
    $onapp_payment = get_onapp_object(
        'ONAPP_Payment',
        $servers[$client['server_id']]['ipaddress'],
        $servers[$client['server_id']]['username'],
        $servers[$client['server_id']]['password']
    );

    $onapp_payment->_user_id = $client['onapp_user_id'];
    $onapp_payment->_amount = $client['amount'];
    $onapp_payment->_invoice_number = $invoice_id;

    $onapp_payment->save();

    $client_amount_query = "SELECT
            SUM(tblinvoiceitems.amount) AS amount
        FROM
            tblinvoices
            LEFT JOIN tblinvoiceitems ON tblinvoiceitems.invoiceid = tblinvoices.id
            LEFT JOIN tblonappusers ON tblinvoiceitems.userid = tblonappusers.client_id
        WHERE
            tblonappusers.client_id = ".$client['client_id']."
            AND tblinvoiceitems.type = 'onappusers'
            AND tblinvoices.status = 'Unpaid'
        GROUP BY tblonappusers.client_id, tblonappusers.server_id";
    $client_amount_result = full_query($client_amount_query);
    $client_amount = mysql_fetch_assoc($client_amount_result);

    if (!$client_amount['amount']) {
        serverunsuspendaccount($client['service_id']);
    }

}

add_hook("DailyCronJob",1,"autosuspend_onappusers","");
add_hook("InvoicePaid",1,"unsuspend_user","");
?>