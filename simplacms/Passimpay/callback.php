<?php

chdir('../../');

// CORE
require_once('api/Simpla.php');
// UTILS
require_once 'Logger.php';
require_once('utils/PassimpayApiClient.php');

// log input request
$payload = !empty($_POST) ? json_encode($_POST) : file_get_contents('php://input');
Logger::info("INPUT REQUEST WITH METHOD ${$_SERVER['REQUEST_METHOD']} --- PAYLOAD: $payload");

try {
    processWebhook();
} catch (Exception $e) {
    Logger::error('Error while process webhook: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    sendResponse('Error while process webhook.');
}

function processWebhook()
{
    $simpla = new Simpla();
    // parse params
    $orderId = parsePassimpayPayload()['order_id'];
    $currencyId = parsePassimpayPayload()['payment_id'];
    $amountX = parsePassimpayPayload()['amount']; // in currency of pay (BTC or TRN or ETH or etc...), so need convert to USD

    // get order from db
    $order = $simpla->orders->get_order((int)$orderId);
    if (empty($order)) {
        Logger::warning("Order with id '$orderId' not found.");
        sendResponse('Order not found.', 404);
    }
    // get pay method from db
    $paymentMethod = $simpla->payment->get_payment_method($order->payment_method_id);
    if (empty($paymentMethod)) {
        Logger::error("Unknown order pay method with id '{$order->payment_method_id}'.");
        sendResponse('Unknown pay method.', 500);
    }

    $orderPrice = $simpla->money->convert($order->total_price, $paymentMethod->currency_id, false);

    $paymentSettings = unserialize($paymentMethod->settings);

    $passimpay = new PassimpayApiClient($paymentSettings['secretKey'], $paymentSettings['platformId']);

    if (!$currency = $passimpay->getCurrency($currencyId)) {
        Logger::error("Failed to get currency with id '$currencyId'.");
        sendResponse('Failed to get currency.', 500);
    }

    // convert paid amount to USD
    $amountUsd = number_format($amountX * $currency['rate_usd'], 2, '.', '');
    Logger::info("Converted $amountX {$currency['currency']} to $amountUsd USD.");

    // check hash
    $payload = parsePassimpayPayload();
    $hash = parsePassimpayHash();
    if (!$passimpay->checkHash($payload, $hash)) {
        Logger::warning("Bad hash: $hash.");
        sendResponse('Bad hash.', 400);
    }

    // check order status in shop
    if ($order->paid) {
        Logger::info("This order with id '{$order->id}' already paid.");
        sendResponse('This order already paid.');
    }

    // handle part of pay
    if ($orderPrice > $amountUsd) {
        $currency = $simpla->money->get_currencies()[$paymentMethod->currency_id];
        $msg = "Passimpay: Payed only part of order #{$order->id}: $amountUsd {$currency->code}";
        $updatedNote = $order->note . "\n$msg";
        $simpla->orders->update_order($order->id, ['note' => $updatedNote]);
        Logger::info("Success paid part of order: $msg");
    }

    // check order status
    $orderInfo = $passimpay->getOrderStatus($orderId);
    // handle errors
    if (!isset($orderInfo['result']) || $orderInfo['result'] != 1) {
        $error = $orderInfo['message'];
        Logger::error("Failed to get order status for $orderId: $error.");
        sendResponse('Failed to get order status.', 500);
    } elseif ($orderInfo['status'] == PassimpayApiClient::ORDER_STATUS_ERROR) {
        Logger::error('Order status is error.');
        sendResponse('Order status is error.', 400);
    } elseif ($orderInfo['status'] == PassimpayApiClient::ORDER_STATUS_WAIT) {
        Logger::info('Order status is wait.');
        sendResponse('Wait...');
    }

    // set status as paid
    $simpla->orders->update_order($order->id, ['paid' => 1]);
    Logger::info("Order with id '{$order->id}' paid.");
    // get off goods
    $simpla->orders->close($order->id);
    Logger::info("Order with id '{$order->id}' closed.");
    // send email
    $simpla->notify->email_order_user($order->id);
    $simpla->notify->email_order_admin($order->id);
    Logger::info("Sent email.");
    // redirect user to order page
    header('Location: ' . $simpla->config->root_url . '/order/' . $order->url);
}

/**
 * @return array
 */
function parsePassimpayPayload() {

    $simpla = new Simpla();

    $payload = [
        'platform_id' => $simpla->request->post('platform_id', 'integer'),
        'payment_id' => $simpla->request->post('payment_id', 'integer'),
        'order_id' => $simpla->request->post('order_id', 'integer'),
        'amount' => $simpla->request->post('amount'),
        'txhash' => $simpla->request->post('txhash'),
        'address_from' => $simpla->request->post('address_from'),
        'address_to' => $simpla->request->post('address_to'),
        'fee' => $simpla->request->post('fee'),
    ];

    if ($confirmations = $simpla->request->post('confirmations')) {
        $payload['confirmations'] = $confirmations;
    }

    return $payload;
}

/**
 * @return string
 */
function parsePassimpayHash() {

    $simpla = new Simpla();

    return $simpla->request->post('hash', 'string');
}

function sendResponse($userMessage, $code = 200) {

    http_response_code($code);

    die($userMessage);
}

exit();