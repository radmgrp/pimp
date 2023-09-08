<?php
/**
 * Payment notification handler
 */
session_start();

require_once 'functions.php';
require_once 'utils/PassimpayApiClient.php';
require_once 'Logger.php';

$_classPath = "/../../";
$classPath = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR;
include($classPath . "phpshop/class/obj.class.php");
PHPShopObj::loadClass("base");
PHPShopObj::loadClass("order");
PHPShopObj::loadClass("file");
PHPShopObj::loadClass("orm");
PHPShopObj::loadClass("payment");
PHPShopObj::loadClass("modules");
PHPShopObj::loadClass("system");

$PHPShopBase = new PHPShopBase($classPath . "phpshop/inc/config.ini");

class PassimpayPayment extends PHPShopPaymentResult {

    /**
     * @var PassimpayApiClient
     */
    private $passimpay;

    function __construct($passimpay) {
        $this->passimpay = $passimpay;
        $this -> debug = false;
        $this -> log = true;
        $this->option();
        parent::__construct();
    }

    function option() {
        $this->payment_name = 'passimpay';
    }

    /**
     * @return array
     */
    function parsePassimpayPayload() {
        $payload = [
            'platform_id' => (int) $_REQUEST['platform_id'],
            'payment_id' => (int) $_REQUEST['payment_id'],
            'order_id' => (int) $_REQUEST['order_id'],
            'amount' => $_REQUEST['amount'],
            'txhash' => $_REQUEST['txhash'],
            'address_from' => $_REQUEST['address_from'],
            'address_to' => $_REQUEST['address_to'],
            'fee' => $_REQUEST['fee'],
        ];

        if (isset($_REQUEST['confirmations'])) {
            $payload['confirmations'] = $_REQUEST['confirmations'];
        }

        return $payload;
    }

    /**
     * @return string
     */
    function parsePassimpayHash() {
        return $_REQUEST['hash'];
    }

    /**
     * @return boolean
     */
    function check() {

        $payload = $this->parsePassimpayPayload();

        Logger::info('Check input request. PAYLOAD: ' . json_encode($payload));

        $currencyId = $this->parsePassimpayPayload()['payment_id'];
        $amountX = $this->parsePassimpayPayload()['amount']; // in currency of pay (BTC or TRN or ETH or etc...), so need convert to USD

        if (!$currency = $this->passimpay->getCurrency($currencyId)) {
            Logger::error("Failed to get currency with id '$currencyId'.");
            return false;
        }

        // convert paid amount to USD
        $amountUsd = number_format($amountX * $currency['rate_usd'], 2, '.', '');
        Logger::info("Converted $amountX {$currency['currency']} to $amountUsd USD.");
        // get current currency of shop
        $currency = (new PHPShopSystem())->getDefaultValutaIso();
        // convert to shop currency
        $amountValuta = convertToValuta($amountUsd, 'USD', $currency);
        Logger::info("Converted $amountUsd USD to $amountValuta $currency (shop currency).");

        $this->out_summ = $amountValuta;
        $this->inv_id = $orderId = $this->parsePassimpayPayload()['order_id'];

        $order = $this->getOrderById($this->inv_id);
        $orderPrice = (float) $order['sum'];

        // check hash
        $hash = $this->parsePassimpayHash();
        if (!$this->passimpay->checkHash($payload, $hash)) {
            return false;
        }
        // handle part of pay
        if ($orderPrice > $amountValuta) {
            Logger::info("Passimpay: Payed only part of order #$orderId: $amountValuta $currency");
        }
        // check order status
        $orderInfo = $this->passimpay->getOrderStatus($orderId);
        // handle errors
        if (!isset($orderInfo['result']) || $orderInfo['result'] != 1) {
            $error = $orderInfo['message'];
            Logger::error("Failed to get order status for $orderId: $error.");
            return false;
        } elseif ($orderInfo['status'] == PassimpayApiClient::ORDER_STATUS_ERROR) {
            Logger::error('Order status is error.');
            return false;
        } elseif ($orderInfo['status'] == PassimpayApiClient::ORDER_STATUS_WAIT) {
            Logger::info('Order status is wait.');
            return false;
        } elseif ($orderInfo['status'] == PassimpayApiClient::ORDER_STATUS_PAID) {
            // set all sum, because order is paid
            $this->out_summ = $orderPrice;
            Logger::info('Order status is paid.');
            return true;
        }

        Logger::warning("Unknown order status: '{$orderInfo['status']}'.");

        return false;
    }

    function getOrderById($inv_id) {
        $PHPShopOrm = new PHPShopOrm($GLOBALS['SysValue']['base']['orders']);
        $PHPShopOrm->debug = $this->debug;
        $data = $PHPShopOrm->select(array('*'), array('uid' => '="' . $this->true_num($inv_id) . '"'), false, array('limit' => 1));
        return $data;
    }

    function done() {
        $this->log();

       header("Location: /done/");
        exit;
    }

    function error($type = 1) {
        $this->log();

        header("Location: /fail/");
        exit;
    }

}

global $SysValue;

$passimpay = new PassimpayApiClient(
    $SysValue['passimpay']['secret_key'],
    $SysValue['passimpay']['platform_id']
);

(new PassimpayPayment($passimpay));