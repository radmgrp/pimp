import requests
import hashlib


class PassimpayApi:
    URL_BASE = 'https://passimpay.io/api'

    URL_BALANCE = f'{URL_BASE}/balance'
    URL_CURRENCIES = f'{URL_BASE}/currencies'
    URL_INVOICE_CREATE = f'{URL_BASE}/createorder'
    URL_INVOICE_STATUS = f'{URL_BASE}/orderstatus'
    URL_PAYMENT_WALLET = f'{URL_BASE}/getpaymentwallet'
    URL_WITHDRAW = f'{URL_BASE}/withdraw'
    URL_TRANSACTION_STATUS = f'{URL_BASE}/transactionstatus'

    def __init__(self, platform_id, secret_key):
        self.platform_id = platform_id
        self.secret_key = secret_key

    def balance(self):
        response = self.request(self.URL_BALANCE)
        if 'result' in response and response['result'] == 1:
            return [response['balance'], None]
        return [None, response['message']]

    def currencies(self):
        response = self.request(self.URL_CURRENCIES)
        if 'result' in response and response['result'] == 1:
            return [response['list'], None]
        return [None, response['message']]

    def invoice(self, id, amount):
        response = self.request(self.URL_INVOICE_CREATE, {'order_id': id, 'amount': amount})
        if 'result' in response and response['result'] == 1:
            return [response['url'], None]
        return [None, response['message']]

    def invoice_status(self, id):
        response = self.request(self.URL_INVOICE_STATUS, {'order_id': id})
        if 'result' in response and response['result'] == 1:
            return [response['status'], None]
        return [None, response['message']]

    def payment_wallet(self, order_id, payment_id):
        params = {'payment_id': payment_id, 'platform_id': self.platform_id, 'order_id': order_id}
        response = self.request(self.URL_PAYMENT_WALLET, params)
        if 'result' in response and response['result'] == 1:
            return [response['address'], None]
        return [None, response['message']]

    def withdraw(self, payment_id, address_to, amount):
        params = {'payment_id': payment_id, 'platform_id': self.platform_id, 'amount': amount, 'address_to': address_to}
        response = self.request(self.URL_WITHDRAW, params)
        if 'result' in response and response['result'] == 1:
            del response['result']
            del response['message']
            return [response, None]
        return [None, response['message']]

    def transaction_status(self, tx_hash):
        response = self.request(self.URL_TRANSACTION_STATUS, {'txhash': tx_hash})
        if 'result' in response and response['result'] == 1:
            del response['result']
            del response['message']
            return [response, None]
        return [None, response['message']]

    def request(self, url, parameters=None):
        if not self.secret_key:
            raise Exception('Passimpay: secret key cannot be empty.')
        if not self.platform_id:
            raise Exception('Passimpay: platform id cannot be empty.')

        payload = parameters.copy() if parameters else {}
        payload['platform_id'] = self.platform_id
        payload['hash'] = hashlib.sha256('&'.join([f'{k}={v}' for k, v in payload.items()]).encode()).hexdigest()

        response = requests.post(url, data=payload, headers={'Content-Type': 'application/x-www-form-urlencoded'})

        if response.status_code == 200:
            return response.json()
        else:
            raise Exception(f'Passimpay request failed with status code {response.status_code}: {response.text}')


# Пример использования:
platform_id = 1
secret_key = 'your-api-key'
order_id = 1
payment_id = 1

api = PassimpayApi(platform_id, secret_key)
balance, error = api.balance()
if error is not None:
    print(f'Error: {error}')
else:
    print(f'Balance: {balance}')