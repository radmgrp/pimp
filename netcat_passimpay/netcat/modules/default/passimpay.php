<?php

/*ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);*/


class nc_payment_system_passimpay extends nc_payment_system {

    protected $automatic = true;

    protected $url_currency = 'http://www.cbr.ru/scripts/XML_daily.asp';

    protected $accepted_currencies = array('RUR');

    protected $settings = array(
        'ApiKey' => null,
        'MerchantId' => null,
        'SuccessUrl' => null,
        'FailUrl' => null
    );

    /*protected $request_parameters = array(
        'PhoneNumber' => null
    );*/

    /**
     * Отправка счета на сервер Passimpay и переадресация клиента для оплаты
     *
     * @param nc_payment_invoice $invoice
     */
    public function execute_payment_request(nc_payment_invoice $invoice) {
        //$phone_number = $this->get_request_parameter('PhoneNumber');
        //$current_site = nc_core::get_object()->catalogue->get_current();

        $platform_id = (string)$this->get_setting('MerchantId');
        $apikey = (string)$this->get_setting('ApiKey');
        $amount = $invoice->get_amount();
        $order_id = (string)$invoice->get_id();

        $obj = simplexml_load_file($this->url_currency);

        $rates = array(
            'date' => (string)$obj->attributes()['Date']
        );
        foreach ($obj->Valute as $key => $currency) {
            $rates += array((string)$currency->CharCode => (str_replace(",", ".", (string)$currency->Value) / (string)$currency->Nominal));
        }

        $amount = (string)round($amount / $rates['USD'], 2);

        $payload = http_build_query(['platform_id' => $platform_id, 'order_id' => $order_id, 'amount' => $amount],'', '&');
        $hash = hash_hmac('sha256', $payload, $apikey);

        $data = [
            'platform_id' => $platform_id,
            'order_id' => $order_id,
            'amount' => $amount,
            'hash' => $hash
        ];

        $post_data = http_build_query($data, '', '&');

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($curl, CURLOPT_URL, 'https://passimpay.io/api/createorder');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $result = curl_exec($curl);
        curl_close($curl);

        $result = json_decode($result, true);

        if (isset($result['result']) && $result['result'] == 1)
        {
            $url = $result['url'];
        }
        // В случае ошибки
        else
        {
            $error = $result['message']; // Текст ошибки
            echo $error;
            exit;
        }

        header('Location: ' . $url);
    }

    /**
     * Действия при обработке каллбека от Passimpay
     *
     * @param nc_payment_invoice $invoice
     */
    public function on_response(nc_payment_invoice $invoice = null) {

        switch ($this->get_response_value('status')) {
            case 'success':
                //$this->on_payment_success($invoice);
                $SuccessUrl = $this->get_setting('SuccessUrl');
                if (empty($SuccessUrl)) $SuccessUrl = "/";
                header('Location: ' . $SuccessUrl);
                break;
            case 'fail':
                $this->on_payment_failure($invoice);

                $site_id = nc_core::get_object()->catalogue->id();
                $netshop = nc_netshop::get_instance($site_id);
                $order = $netshop->load_order($invoice->get('order_id'));
                $order->set('Status', 2)->save();

                $FailUrl = $this->get_setting('FailUrl');
                if (empty($FailUrl)) $FailUrl = "/";
                header('Location: ' . $FailUrl);
                break;
            case 'notify':

                $apikey = (string)$this->get_setting('ApiKey');
                $hash = $_POST['hash'];

                $data = [
                    'platform_id' => (int) $_POST['platform_id'], // ID платформы
                    'payment_id' => (int) $_POST['payment_id'], // ID валюты
                    'order_id' => (int) $_POST['order_id'], // Payment ID Вашей платформы
                    'amount' => $_POST['amount'], // сумма транзакции
                    'txhash' => $_POST['txhash'], // Хэш или идентификатор транзакции. ID транзакции можно найти в истории транзакций PassimPay в вашем аккаунте.
                    'address_from' => $_POST['address_from'], // адрес отправителя
                    'address_to' => $_POST['address_to'], // адрес получателя
                    'fee' => $_POST['fee'], // комиссия сети
                ];

                if (isset($_POST['confirmations']))
                {
                    $data['confirmations'] = $_POST['confirmations']; // количество подтверждений сети (Bitcoin, Litecoin, Dogecoin, Bitcoin Cash)
                }

                $payload = http_build_query($data,'', '&');

                if (!isset($hash) || hash_hmac('sha256', $payload, $apikey) != $hash)
                {
                    $this->on_payment_failure($invoice);
                    return false;
                }

                $order_id = isset($_POST['order_id'])
                    ? (int)$_POST['order_id']
                    : 0;

                if (!$order_id) {
                    exit;
                }

                $url = 'https://passimpay.io/api/orderstatus';
                $platform_id = (int) $_POST['platform_id']; // ID платформы
                $order_id = (int) $_POST['order_id']; // Payment ID Вашей платформы

                $payload = http_build_query(['platform_id' => $platform_id, 'order_id' => $order_id ],'', '&');
                $hash = hash_hmac('sha256', $payload, $apikey);

                $data = [
                    'platform_id' => $platform_id,
                    'order_id' => $order_id,
                    'hash' => $hash
                ];

                $post_data = http_build_query($data,'', '&');

                $curl = curl_init();
                curl_setopt($curl, CURLOPT_HEADER, false);
                curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
                curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                $result = curl_exec($curl);
                curl_close( $curl );

                $result = json_decode($result, true);

                // Варианты ответов
                // В случае успеха
                if (isset($result['result']) && $result['result'] == 1)
                {
                    $status = $result['status']; // paid, error, wait
                    if ($status == 'paid'){

                        $site_id = nc_core::get_object()->catalogue->id();
                        $netshop = nc_netshop::get_instance($site_id);
                        $order = $netshop->load_order($invoice->get('order_id'));
                        $order->set('Status', 3)->save();

                        $invoice->set('status', nc_payment_invoice::STATUS_SUCCESS)->save();

                    }
                }
                // В случае ошибки
                else
                {
                    $this->on_payment_failure($invoice);
                    return false;
                }
        }

        return true;
    }

    /**
     * Проверка настроек платежной системы при запросе на оплату
     */
    public function validate_payment_request_parameters() {

    }


    /**
     * Обработка параметров входящего внешнего запроса
     *
     * @param nc_payment_invoice|null $invoice
     * @return bool
     */
    public function validate_payment_callback_response(nc_payment_invoice $invoice = null) {
        if ($_SERVER['PHP_AUTH_USER'] == $this->get_setting('ShopID')) {
            if ($_SERVER['PHP_AUTH_PW'] == $this->get_setting('ApiPullPassword')) {
                return true;
            }
        }

        $this->return_status(400);
        exit;
    }

    /**
     * Получение объекта nc_payment_invoice по параметрам входящего внешнего запроса
     *
     * @return nc_payment_invoice
     */
    public function load_invoice_on_callback() {

        $order_id = $this->get_response_value('order_id');

        $invoice_id = intval($order_id);
        return $this->load_invoice($invoice_id);
    }

}
