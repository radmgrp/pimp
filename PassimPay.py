import hmac

import requests
import json
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
        print(self.request)
        print(response)
        if 'result' in response and response['result'] == 1:
            return response['balance'], None
        return None, response['message']

    def currencies(self):
        response = self.request(self.URL_CURRENCIES)
        if 'result' in response and response['result'] == 1:
            return response['list'], None
        return None, response['message']

    def invoice(self, id, amount):
        data = {
            'order_id': id,
            'amount': amount
        }
        response = self.request(self.URL_INVOICE_CREATE, data)
        if 'result' in response and response['result'] == 1:
            return response['url'], None
        return None, response['message']

    def invoice_status(self, id):
        data = {'order_id': id}
        response = self.request(self.URL_INVOICE_STATUS, data)
        if 'result' in response and response['result'] == 1:
            return response['status'], None
        return None, response['message']

    def payment_wallet(self, order_id, payment_id):
        data = {
            'payment_id': payment_id,
            'platform_id': self.platform_id,
            'order_id': order_id
        }
        response = self.request(self.URL_PAYMENT_WALLET, data)
        if 'result' in response and response['result'] == 1:
            return response['address'], None
        return None, response['message']

    def withdraw(self, payment_id, address_to, amount):
        data = {
            'payment_id': payment_id,
            'platform_id': self.platform_id,
            'amount': amount,
            'address_to': address_to
        }
        response = self.request(self.URL_WITHDRAW, data)
        if 'result' in response and response['result'] == 1:
            del response['result']
            del response['message']
            return response, None
        return None, response['message']

    def transaction_status(self, tx_hash):
        data = {'txhash': tx_hash}
        response = self.request(self.URL_TRANSACTION_STATUS, data)
        if 'result' in response and response['result'] == 1:
            del response['result']
            del response['message']
            return response, None
        return None, response['message']

    def request(self, url, data=None):
        if not self.secret_key:
            raise Exception('Passimpay: secret key can not be empty.')

        if not self.platform_id:
            raise Exception('Passimpay: platform id can not be empty.')

        payload = {'platform_id': self.platform_id}
        if data:
            payload.update(data)
        payload_str = "&".join(f"{key}={value}" for key, value in payload.items()).encode('utf-8')
        hash_value = hmac.new(secret_key.encode('utf-8'), payload_str, hashlib.sha256).hexdigest()

        data = {
            'platform_id': platform_id,
            'hash': hash_value,
        }

        response = requests.post(url, data=data)
        payload['hash'] = hashlib.sha256(json.dumps(payload).encode()).hexdigest()

        headers = {'Content-Type': 'application/json'}
        # response = requests.post(url, json=payload, headers=headers)

        return response.json()


platform_id = '305'
secret_key = '72b214-28c6d1-78e18e-dcea3a-6851a7'

passimpay_api = PassimpayApi(platform_id, secret_key)
print(passimpay_api.balance())
balance, error = passimpay_api.balance()
print(balance)
if error is not None:
    print(f'Произошла ошибка при проверке баланса: {error}')
else:
    print(f'Баланс: {balance}')

currencies, error = passimpay_api.currencies()
if error is not None:
    print(f'Произошла ошибка при получении списка валют: {error}')
else:
    print(f'Список доступных валют: {currencies}')

### invoice
order_id = 'your_order_id'

status, error = passimpay_api.invoice_status(order_id)
if error is not None:
    print(f'Error checking invoice status: {error}')
else:
    print(f'Invoice Status: {status}')


### invoice_status
order_id = 'your_order_id'
payment_id = 'your_payment_id'

# Get the payment wallet address
address, error = passimpay_api.payment_wallet(order_id, payment_id)
if error is not None:
    print(f'Error getting payment wallet address: {error}')
else:
    print(f'Payment Wallet Address: {address}')

### payment_wallet
# Предполагая, что у вас есть 'payment_id', 'address_to', and 'amount'
payment_id = 'your_payment_id'
address_to = 'recipient_address'
amount = 100.0  # Укажите сумму, которую хотите вывести

# Withdraw
response, error = passimpay_api.withdraw(payment_id, address_to, amount)
if error is not None:
    print(f'Error withdrawing funds: {error}')
else:
    print(f'Withdrawal Response: {response}')

# Предполагая, что у вас есть 'tx_hash' (transaction hash)
tx_hash = 'your_transaction_hash'

# transaction_status
response, error = passimpay_api.transaction_status(tx_hash)
if error is not None:
    print(f'Error checking transaction status: {error}')
else:
    print(f'Transaction Status: {response}')