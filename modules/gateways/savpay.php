<?php

defined("DEFINE_MY_ACCESS") or die('<h1 style="color: #C00; text-align: center;"><strong>Restricted Access</strong></h1>');

if (!isset($_SESSION)) { $_SESSION = []; }

/**
 * SavPay Gateway config for DHRU
 */
function savpay_config()
{
    return [
        'name' => [
            'Type'  => 'System',
            'Value' => 'SavPay',
        ],
        'api_key' => [
            'Name' => 'SavPay API Key',
            'Type' => 'text',
            'Size' => '80',
            'Description' => 'Enter your SavPay API Key.',
        ],
        // Optional: if later you want to use SECRET-KEY / BRAND-KEY, you can add them here.
        /*
        'secret_key' => [
            'Name' => 'SavPay Secret Key',
            'Type' => 'text',
            'Size' => '80',
        ],
        'brand_key' => [
            'Name' => 'SavPay Brand Key',
            'Type' => 'text',
            'Size' => '80',
        ],
        */
    ];
}

/**
 * SavPay link renderer (button on invoice)
 */
function savpay_link($params)
{
    static $alreadyPrinted = false;

    // Prevent duplicate output
    if ($alreadyPrinted) {
        return "";
    }
    $alreadyPrinted = true;

    global $lng_languag;

    $url    = $params['systemurl'] . 'modules/gateways/callback/' . $params['paymentmethod'] . '.php';
    $invId  = $params['invoiceid'];
    $payTxt = isset($lng_languag["invoicespaynow"]) ? $lng_languag["invoicespaynow"] : 'Pay Now';

    // Error box (from session or URL)
    $errorBox = savpay_errormessage();

    return <<<HTML
<div id="savpay-wrapper">
    $errorBox
    <form method="GET" action="$url" style="margin-top:10px;">
        <input type="hidden" name="action" value="init">
        <input type="hidden" name="id" value="$invId">
        <input class="btn btn-primary" type="submit" value="$payTxt">
    </form>
</div>
HTML;
}

/**
 * Error message helper (shows last error from session or URL)
 */
function savpay_errormessage()
{
    if (!isset($_SESSION)) { $_SESSION = []; }

    $errorCode = '';

    if (!empty($_SESSION['savpay_error'])) {
        $errorCode = htmlspecialchars($_SESSION['savpay_error'], ENT_QUOTES, 'UTF-8');
        $_SESSION['savpay_error'] = '';
    } elseif (!empty($_REQUEST['errorCode'])) {
        $errorCode = htmlspecialchars(urldecode($_REQUEST['errorCode']), ENT_QUOTES, 'UTF-8');
    }

    if (empty($errorCode)) {
        return "";
    }

    return <<<HTML
<div id="savpayErrorBox" style="
    margin:10px 0;
    padding:10px;
    border-radius:4px;
    background:#ffe5e5;
    border:1px solid #ffbdbd;
    color:#900;
    font-size:13px;
    position:relative;
">
    <strong>Error:</strong> $errorCode
    <span id="savpayCloseErr" style="
        position:absolute;
        top:6px;
        right:8px;
        cursor:pointer;
        font-weight:bold;
        font-size:16px;
        color:#900;
    ">&times;</span>
</div>

<script>
document.addEventListener("DOMContentLoaded", function(){
    var closeBtn = document.getElementById("savpayCloseErr");
    var box = document.getElementById("savpayErrorBox");
    if(closeBtn && box){
        closeBtn.onclick = function(){ box.remove(); };
    }

    // Clean URL (remove errorCode etc)
    if(history.replaceState){
        let clean = window.location.href.split("?")[0].split("#")[0];
        history.replaceState({}, "", clean);
    }
});
</script>
HTML;
}

