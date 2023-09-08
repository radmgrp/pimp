<?php

use UmiCms\Service;

require_once __DIR__ . '/../api/Passimpay/Client/PassimpayApiClient.php';
class passimpayPayment extends payment {

    const CURRENCY_CODE_USD = 'USD';

    const EVENT_DECLINED = 'declined';

    public function validate() {
        return true;
    }

    public static function getOrderId() {
        return (int) getRequest('order_id');
    }

    /**
     * @inheritDoc
     * Создает платеж и возвращает адрес, куда необходимо отправиться пользователю.
     * Устанавливает заказу статус оплаты "Инициализирована".
     */
    public function process($template = null) {
        $order = $this->order;
        $out_amount = number_format($this->order->getActualPrice(), 2, '.', '');

        $currencies = Service::CurrencyFacade();
        $currencyUsd = $currencies->getByCode(self::CURRENCY_CODE_USD);
        $out_amount_usd = $currencies->calculate($out_amount, $currencies->getCurrent(), $currencyUsd);
        $order_id = $this->order->getId();

        $response = $this->getClient()
            ->createOrder($order_id, $out_amount_usd);

        if ($response['result'] != 1) {
            throw new \expectObjectException("Failed to create checkout url :(");
        }
        
        $order->order();
        $order->setPaymentStatus('initialized', true);

        $customer = customer::get();
        $customer->setValue('last_order', []);
        $customer->commit();

        $buffer = Service::Response()
            ->getCurrentBuffer();
        $buffer->clear();
        $buffer->setHeader('Location', $response['url']);
        return $buffer;
    }

    /**
     * @inheritDoc
     * Получает запрос от Passimpay и валидирует параметры платежа.
     * В зависимости от результата валидации отправляет запрос на подтверждение или отклонение платежа.
     * Устанавливает заказу статус оплаты "Проверена" или "Отклонена".
     */
    public function poll() {

        if (isset($_GET['event'])) {
            return $this->handleEvent($_GET['event']);
        }

        $payload = $this->parsePassimpayPayload();
        $orderId = $payload['order_id'];
        $order = $this->order;
        $client = $this->getClient();

        // check hash
        $hash = $this->parsePassimpayHash();
        if (!$client->checkHash($payload, $hash)) {
            throw new \expectObjectException('Invalid hash');
        }

        // check order status
        $orderInfo = $client->getOrderStatus($orderId);
        // handle errors
        if (!isset($orderInfo['result']) || $orderInfo['result'] != 1) {
            $error = $orderInfo['message'];
            throw new \expectObjectException("Failed to get order status for $orderId: $error.");
        } elseif ($orderInfo['status'] == PassimpayApiClient::ORDER_STATUS_ERROR) {
            throw new \expectObjectException('Order status is error.');
        } elseif ($orderInfo['status'] == PassimpayApiClient::ORDER_STATUS_WAIT) {
            $buffer = Service::Response()
                ->getCurrentBuffer();
            $buffer->clear();
            $buffer->contentType('text/plain');
            $buffer->push('200 OK Order status is wait.');
            return $buffer;
        } elseif ($orderInfo['status'] == PassimpayApiClient::ORDER_STATUS_PAID) {

            $order->setPaymentStatus('accepted');
            $order->commit();

            $buffer = Service::Response()
                ->getCurrentBuffer();
            $buffer->clear();
            $buffer->contentType('text/plain');
            $buffer->push('200 OK for Passimpay');

            return $buffer;
        }

        throw new \expectObjectException("Unknown order status: {$orderInfo['status']}.");
    }

    private function handleEvent($event) {
        $order = $this->order;

        switch ($event) {
            case self::EVENT_DECLINED: {

                $order->setPaymentStatus('declined');
                $order->setOrderStatus('canceled');
                $order->commit();

                $buffer = Service::Response()
                    ->getCurrentBuffer();
                $buffer->clear();
                $buffer->setHeader('Location', parent::getFailUrl());
                return $buffer;
            }
        }

        throw new \expectObjectException(getLabel('error-unexpected-exception'));
    }

    private static function parsePassimpayPayload(): array {
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

    private static function parsePassimpayHash(): string {
        return $_REQUEST['hash'];
    }

    /**
     * @inheritDoc
     * @return string
     * @throws ErrorException
     * @throws coreException
     * @throws publicException
     */
    public function approvePayment() : string {
        if (!$this->order instanceof order) {
            throw new ErrorException('You should pass order to class constructor');
        }

        $order = $this->order;

        $order->setPaymentStatus('accepted');
        $order->commit();
        return (string) getLabel('label-payment-approve');
    }

    /**
     * @inheritDoc
     * @return string
     * @throws ErrorException
     * @throws publicException
     */
    public function cancelPayment() : string {
        if (!$this->order instanceof order) {
            throw new ErrorException('You should pass order to class constructor');
        }

        $order = $this->order;

        try {
            $order->setPaymentStatus('declined');
            $order->commit();
            return (string) getLabel('label-payment-cancel');
        } catch (Exception $exception) {
            if (contains($exception->getMessage(), 'You can only cancel payments with the waiting_for_capture')) {
                return (string) getLabel('label-error-yandex-payment-cancel-bad-status');
            }

            throw $exception;
        }
    }

    /**
     * @inheritDoc
     * @return string
     * @throws ErrorException
     * @throws coreException
     * @throws publicException
     */
    public function refundPayment() : string {
        if (!$this->order instanceof order) {
            throw new ErrorException('You should pass order to class constructor');
        }

        $order = $this->order;

        $order->setPaymentStatus('refund');
        $order->commit();
        return (string) getLabel('label-payment-refund');
    }

    /**
     * Возвращает клиента для интеграции
     * @return PassimpayApiClient
     * @throws publicException
     */
    protected function getClient() {
        $object = $this->object;
        $platformId = (string) $object->getValue('passimpay_platform_id');
        $apiKey = (string) $object->getValue('passimpay_api_key');

        if ($platformId === '' || $apiKey === '') {
            throw new publicException(getLabel('error-payment-wrong-settings'));
        }

        return new PassimpayApiClient($apiKey, $platformId);
    }

}

?>