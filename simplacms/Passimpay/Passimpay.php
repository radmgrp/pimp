<?php
// CORE
require_once('api/Simpla.php');
// UTILS
require_once('utils/PassimpayApiClient.php');
require_once('Logger.php');

class Passimpay extends Simpla
{
    public function checkout_form($orderId, $buttonText = null)
    {
        if(empty($buttonText)) {
            $buttonText = 'Checkout';
        }

        $order = $this->orders->get_order((int)$orderId);

        $paymentMethod = $this->payment->get_payment_method($order->payment_method_id);
        $paymentSettings = $this->payment->get_payment_settings($paymentMethod->id);
        $currency = $this->money->get_currencies()[$paymentMethod->currency_id];

        $passimpay = new PassimpayApiClient($paymentSettings['secretKey'], $paymentSettings['platformId']);

        // convert order price to Passimpay currency (USD)
        $price = $this->money->convert($order->total_price, $paymentMethod->currency_id, false);
        $amount = number_format($price, 2, '.', '');

        if (!$url = $this->createPayUrl($passimpay, $orderId, $amount)) {
            Logger::error('Failed to create pay url.');
            return '<b style="color: red">Failed to create checkout url :(</b>';
        }

        Logger::info("Created new pay url: $url. With amount: $amount {$currency->code}.");

        // button
        return '<form action="'.$url.'"/>'.
                   '<input type=submit class=checkout_button value="'.$buttonText.'">'.
               '</form>';
    }

    /**
     * @param PassimpayApiClient $passimpay
     * @param string|int $order_id
     * @param double $amount
     * @return string|null
     */
    private function createPayUrl($passimpay, $orderId, $amount)
    {
        $result = $passimpay->createOrder($orderId, $amount);

        if (!isset($result['result']) || $result['result'] != 1) {
            return null;
        }

        return $result['url'];
    }

}