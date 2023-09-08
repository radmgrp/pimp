<?php
class PassimpayApiClient {

    const BASE_API_URL = 'https://passimpay.io/api';

    const ORDER_STATUS_PAID = 'paid';
    const ORDER_STATUS_WAIT = 'wait';
    const ORDER_STATUS_ERROR = 'error';

    /** @var string */
    private $secretKey;
    /** @var int */
    private $platformId;

    /**
     * @param string $secretKey
     * @param int $platformId
     */
    public function __construct($secretKey, $platformId) {
        $this->secretKey = $secretKey;
        $this->platformId = $platformId;
    }

    private function calculateHash($payload) {
        return hash_hmac('sha256', http_build_query($payload), $this->secretKey);
    }

    /**
     * @param string $endpoint
     * @param array $params
     * @return array
     */
    public function sendRequest($endpoint, $params = []) {

        $payload = array_merge(
            ['platform_id' => $this->platformId],
            $params
        );

        $postData = array_merge(
            ['hash' => $this->calculateHash($payload)],
            $payload
        );

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        // set payload
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postData));
        // set url
        curl_setopt($curl, CURLOPT_URL, self::BASE_API_URL . '/' . $endpoint);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response, true);
    }

    /**
     * @param string|int $orderId
     * @param double $amount
     * @return array
     */
    public function createOrder($orderId, $amount) {
        return $this->sendRequest('createorder', [
            'order_id' => $orderId,
            'amount' => $amount,
        ]);
    }

    /**
     * @param string $txhash
     * @return array
     */
    public function getTransactionStatus($txhash) {
        return $this->sendRequest('transactionstatus', [
            'txhash' => $txhash,
        ]);
    }

    /**
     * @param string|int $orderId
     * @see self::ORDER_STATUS_PAID
     * @see self::ORDER_STATUS_WAIT
     * @see self::ORDER_STATUS_ERROR
     * @return array
     */
    public function getOrderStatus($orderId) {
        return $this->sendRequest('orderstatus', [
            'order_id' => $orderId,
        ]);
    }

    public function getCurrencies() {
        return $this->sendRequest('currencies');
    }

    /**
     * @param int $currencyId
     * @return array|null
     */
    public function getCurrency($currencyId) {
        $response = $this->getCurrencies();

        if (!isset($response['result']) || $response['result'] != 1) {
            return null;
        }

        foreach ($response['list'] as $currency) {
            if ($currency['id'] == $currencyId) {
                return $currency;
            }
        }

        return null;
    }

    /**
     * @param array $payload
     * @param string $hash
     * @return bool
     */
    public function checkHash($payload, $hash) {
        return $this->calculateHash($payload) == $hash;
    }
}