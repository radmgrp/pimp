<?php

if(empty($GLOBALS['SysValue'])) exit(header("Location: /"));

/** @var $SysValue array */

require_once 'functions.php';
require_once 'utils/PassimpayApiClient.php';
require_once 'Logger.php';

if (!defined("OBJENABLED")) {
    require_once(dirname(__FILE__) . "/product.class.php");
}

// initialize variable
$secret_key = $SysValue['passimpay']['secret_key'];
$platform_id = $SysValue['passimpay']['platform_id'];
//параметры магазина
$mrh_ouid = explode("-", $_POST['ouid']);
$inv_id = intval($mrh_ouid[0] . "" . $mrh_ouid[1]);     //номер счета

$total = $GLOBALS['SysValue']['other']['total'];

$currency = $GLOBALS['PHPShopSystem']->getDefaultValutaIso(); // current currency
if($currency !== 'USD') {
    $total = convertToUsd($total, $currency);
}

$passimpay = new PassimpayApiClient($secret_key, $platform_id);

if (!$url = createPayUrl($passimpay, $inv_id, $total)) {
    Logger::error('Failed to create pay url.');
    $disp = getErrorMsg('Failed to create checkout url :(');
} else {
    Logger::info("Created new pay url: $url; With amount: $total USD; inv_id: $inv_id; ouid: {$_POST['ouid']}.");
    $disp = getForm($url);
}

