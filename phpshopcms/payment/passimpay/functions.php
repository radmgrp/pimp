<?php
/**
 * @param PassimpayApiClient $passimpay
 * @param string|int $order_id
 * @param double $amount
 * @return string|null
 */
function createPayUrl($passimpay, $orderId, $amount)
{
    $result = $passimpay->createOrder($orderId, $amount);

    if (!isset($result['result']) || $result['result'] != 1) {
        return null;
    }

    return $result['url'];
}

/**
 * @param double $price
 * @param string $fromValuta
 * @param string $toValuta
 * @return string
 */
function convertToValuta($price, $fromValuta, $toValuta) {
    $fromCurrency = (new PHPShopOrm('phpshop_valuta'))->getOne(['*'], ['iso' => '="'.$fromValuta.'"']);
    $toCurrency = (new PHPShopOrm('phpshop_valuta'))->getOne(['*'], ['iso' => '="'.$toValuta.'"']);

    if(!isset($fromCurrency['id'])) {
        Logger::error("'$fromCurrency' currency not found. Please add this currency in admin panel: /phpshop/admpanel/admin.php?path=system.currency");
        // TODO: add handle exception
        return null;
    } elseif(!isset($toCurrency['id'])) {
        Logger::error("'$toValuta' currency not found. Please add this currency in admin panel: /phpshop/admpanel/admin.php?path=system.currency");
        return null;
    }
    // convert to <Valuta>
    $priceValuta = (float) $price *  (1 / (float) $fromCurrency['kurs'] * (float) $toCurrency['kurs']);
    return number_format($priceValuta, 2, '.', '');
}

/**
 * @param double $price
 * @param string $fromValuta
 * @return string
 */
function convertToUsd($price, $fromValuta) {
    return convertToValuta($price, $fromValuta, 'USD');
}

function getErrorMsg($msg) {
    return "
    <div align='center'>
        <p><img src='/payment/passimpay/assets/logo_en.svg' height='75' border='0'></p>
        <p><br></p>
    
        <div class=\"alert alert-danger\" role=\"alert\">
            $msg
        </div>
    </div>";
}

/**
 * @param string $payUrl
 * @return string html form
 */
function getForm($payUrl) {
    // output HTML page with a button for payment
    return "
    <div align='center'>
        <p><img src='/payment/passimpay/assets/logo_en.svg' height='75' border='0'></p>
        <p><br></p>
    
        <form id=pay name=pay method='POST' action='".$payUrl."' name='pay'>
            <table>
                <tr>
                    <td>
                        <a class='btn btn-success' href='javascript:pay.submit();'>Pay via payment system</a>
                    </td>
                </tr>
            </table>
        </form>
    </div>";
}