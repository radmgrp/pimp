import hmac
import requests
import json
import hashlib
from collections import OrderedDict


class PassimpayApi:
    URL_BASE = ('https://api.passimpay.io')

    def __init__(self, platform_id, secret_key):
        self.platform_id = platform_id
        self.secret_key = secret_key

    def _make_request(self, endpoint, data=None):
        if not self.secret_key:
            raise ValueError('Passimpay: secret key cannot be empty.')

        if not self.platform_id:
            raise ValueError('Passimpay: platform id cannot be empty.')

        url = f'{self.URL_BASE}/{endpoint}'


        payload_str = "&".join(f"{key}={value}" for key, value in data.items()).encode('utf-8')

        hash_value = hmac.new(self.secret_key.encode('utf-8'), payload_str, hashlib.sha256).hexdigest()
        data['hash'] = hash_value

        response = requests.post(url, data=data)

        return response.json()

    def handle_error(self, error_message):
        if error_message:
            print(f'Произошла ошибка: {error_message}')
            return True
        return False

    def check_and_print(self, result, error_message):
        if not self.handle_error(error_message):
            print(result)

    def balance(self):
        response = self._make_request('balance')
        balance, error = response.get('balance'), response.get('message')
        self.check_and_print(f'Баланс: {balance}', error)
        return balance, error

    def currencies(self):
        response = self._make_request('currencies')
        currencies, error = response.get('list'), response.get('message')
        self.check_and_print(f'Список доступных валют: {currencies}', error)
        return currencies, error

    def invoice(self, id, amount):
        data = OrderedDict([
            ('order_id', id),
            ('amount', amount)
        ])
        response = self._make_request('createorder', data)
        url, error = response.get('url'), response.get('message')
        self.check_and_print(f'Invoice URL: {url}', error)
        return url, error

    def invoice_status(self, id):
        data = OrderedDict([
            ('order_id', id)
        ])
        response = self._make_request('orderstatus', data)
        status, error = response.get('status'), response.get('message')
        self.check_and_print(f'Invoice Status: {status}', error)
        return status, error

    def payment_wallet(self, order_id, payment_id):
        data = OrderedDict([
            ('payment_id', payment_id),
            ('platform_id', self.platform_id),
            ('order_id', order_id)
        ])
        response = self._make_request('getpaymentwallet', data)
        address, error = response.get('address'), response.get('message')
        self.check_and_print(f'Payment Wallet Address: {address}', error)
        return address, error

    def withdraw(self, payment_id, address_to, amount):
        data = OrderedDict([
            ('payment_id', payment_id),
            ('platform_id', self.platform_id),
            ('amount', amount),
            ('address_to', address_to)
        ])
        response = self._make_request('withdraw', data)
        self.check_and_print(f'Withdrawal Response: {response}', response.get('message'))
        return response, response.get('message')

    def transaction_status(self, tx_hash):
        data = OrderedDict([
            ('txhash', tx_hash)
        ])
        response = self._make_request('transactionstatus', data)
        self.check_and_print(f'Transaction Status: {response}', response.get('message'))
        return response, response.get('message')


# Пример использования
# platform_id = 'your platform_id'
# secret_key = 'your secret_key'
#
# passimpay_api = PassimpayApi(platform_id, secret_key)
#
# passimpay_api.balance()
# passimpay_api.currencies()
#
# order_id = 'your_order_id'
# passimpay_api.invoice_status(order_id)
#
# order_id = 'your_order_id'
# payment_id = 'your_payment_id'
# passimpay_api.payment_wallet(order_id, payment_id)
#
#
# tx_hash = 'your_transaction_hash'
# passimpay_api.transaction_status(tx_hash)
