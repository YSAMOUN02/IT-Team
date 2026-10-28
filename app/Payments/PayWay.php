<?php

namespace App\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * ABA PayWay e-commerce checkout (Purchase API + Check Transaction API).
 *
 * The browser posts the signed purchase form straight to PayWay, so card
 * details never touch this server. The result is never taken from the
 * browser: every order is confirmed by asking PayWay (checkTransaction).
 */
class PayWay
{
    public const PURCHASE_PATH = '/api/payment-gateway/v1/payments/purchase';

    public const CHECK_PATH = '/api/payment-gateway/v1/payments/check-transaction-2';

    /** Order of the fields in the purchase hash, as documented by ABA. */
    private const PURCHASE_HASH_FIELDS = [
        'req_time', 'merchant_id', 'tran_id', 'amount', 'items', 'shipping', 'firstname', 'lastname',
        'email', 'phone', 'type', 'payment_option', 'return_url', 'cancel_url', 'continue_success_url',
        'return_deeplink', 'currency', 'custom_fields', 'return_params', 'payout', 'lifetime',
        'additional_params', 'google_pay_token', 'skip_success_page',
    ];

    public const PAYMENT_OPTIONS = ['', 'cards', 'abapay_khqr'];

    public function configured(): bool
    {
        return filled(config('payway.merchant_id')) && filled(config('payway.api_key'));
    }

    /**
     * The local test payment page stands in for PayWay. Its "Approve" button
     * gives a course away for free, so it only ever runs on this computer or
     * the office network (localhost / 127.0.0.1 / 192.168.x.x / *.test), never
     * in production or on a real domain, even if APP_ENV was left on "local".
     */
    public function usesTestGateway(): bool
    {
        if (app()->isProduction() || ! $this->onLocalMachine()) {
            return false;
        }

        $forced = config('payway.test_gateway');

        return $forced !== null ? filter_var($forced, FILTER_VALIDATE_BOOL) : ! $this->configured();
    }

    private function onLocalMachine(): bool
    {
        $isLocal = fn (?string $host) => $host !== null && (in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true)
            || str_ends_with($host, '.test') || str_ends_with($host, '.localhost'));

        // Private LAN addresses (192.168.x.x, 10.x.x.x, 172.16–31.x.x) cannot be reached from the internet.
        $isPrivateIp = fn (string $host) => filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;

        return $isLocal(parse_url((string) config('app.url'), PHP_URL_HOST))
            && ((app()->runningInConsole() && ! app()->runningUnitTests()) || $isLocal(request()->getHost()) || $isPrivateIp(request()->getHost()));
    }

    /** Can orders be paid at all right now? */
    public function available(): bool
    {
        return $this->configured() || $this->usesTestGateway();
    }

    public function isSandbox(): bool
    {
        return str_contains(config('payway.base_url'), 'sandbox');
    }

    public function purchaseUrl(): string
    {
        return config('payway.base_url').self::PURCHASE_PATH;
    }

    /**
     * Signed fields for the browser form that opens PayWay's checkout.
     *
     * @param  array{success: string, cancel: string, pushback: string}  $urls
     */
    public function purchaseFields(Order $order, string $paymentOption, array $urls): array
    {
        [$first, $last] = $this->splitName($order->buyer_name);

        $fields = [
            'req_time' => now('UTC')->format('YmdHis'),
            'merchant_id' => (string) config('payway.merchant_id'),
            'tran_id' => $order->number,
            'amount' => $this->amount($order),
            'items' => base64_encode(json_encode([[
                'name' => mb_substr($order->course_title, 0, 100),
                'quantity' => 1,
                'price' => (float) $order->amount,
            ]], JSON_UNESCAPED_UNICODE)),
            'shipping' => '',
            'firstname' => $first,
            'lastname' => $last,
            'email' => mb_substr($order->buyer_email, 0, 50),
            'phone' => mb_substr(preg_replace('/[^\d+]/', '', (string) $order->buyer_phone), 0, 20),
            'type' => 'purchase',
            'payment_option' => in_array($paymentOption, self::PAYMENT_OPTIONS, true) ? $paymentOption : '',
            'return_url' => base64_encode($urls['pushback']),
            'cancel_url' => $urls['cancel'],
            'continue_success_url' => $urls['success'],
            'return_deeplink' => '',
            'currency' => $order->currency,
            'custom_fields' => '',
            'return_params' => $order->number,
            'payout' => '',
            'lifetime' => (string) max(3, (int) config('payway.lifetime')),
            'additional_params' => '',
            'google_pay_token' => '',
            'skip_success_page' => '1',
        ];

        // Empty optional fields count as "" in the hash and are simply not sent.
        $fields['hash'] = $this->hash(array_map(fn ($key) => $fields[$key], self::PURCHASE_HASH_FIELDS));

        return array_filter($fields, fn ($value) => $value !== '');
    }

    /**
     * Ask PayWay for the current state of an order.
     *
     * @return array{status: string, gateway_status: ?string, amount: ?float, currency: ?string, apv: ?string, payload: array}
     */
    public function checkTransaction(Order $order): array
    {
        if ($this->usesTestGateway()) {
            return $this->testResult($order);
        }
        if (! $this->configured()) {
            throw new RuntimeException('PayWay is not configured (PAYWAY_MERCHANT_ID / PAYWAY_API_KEY).');
        }

        $reqTime = now('UTC')->format('YmdHis');
        $merchant = (string) config('payway.merchant_id');

        $response = Http::asJson()->acceptJson()->timeout(20)->retry(2, 500, throw: false)
            ->post(config('payway.base_url').self::CHECK_PATH, [
                'req_time' => $reqTime,
                'merchant_id' => $merchant,
                'tran_id' => $order->number,
                'hash' => $this->hash([$reqTime, $merchant, $order->number]),
            ]);

        $json = $response->json() ?? [];
        $code = (string) data_get($json, 'status.code', '');

        if ($code === '6') { // transaction not found: the buyer has not finished paying
            return $this->result('pending', 'NOT_FOUND', $json);
        }
        if (! $response->successful() || ! in_array($code, ['00', '0'], true)) {
            throw new RuntimeException('PayWay check failed: '.(data_get($json, 'status.message') ?: 'HTTP '.$response->status()));
        }

        $gatewayStatus = strtoupper((string) data_get($json, 'data.payment_status'));
        $status = match ($gatewayStatus) {
            'APPROVED', 'PRE-AUTH' => 'paid',
            'DECLINED' => 'failed',
            'CANCELLED' => 'cancelled',
            'REFUNDED' => 'refunded',
            default => 'pending',
        };

        return $this->result($status, $gatewayStatus, $json,
            data_get($json, 'data.payment_amount', data_get($json, 'data.total_amount')),
            data_get($json, 'data.payment_currency'),
            data_get($json, 'data.apv'),
        );
    }

    /** Base64 of HMAC-SHA512 over the concatenated values, keyed with the API key. */
    public function hash(array $values): string
    {
        return base64_encode(hash_hmac('sha512', implode('', $values), (string) config('payway.api_key'), true));
    }

    private function amount(Order $order): string
    {
        return strtoupper($order->currency) === 'KHR'
            ? number_format((float) $order->amount, 0, '.', '')
            : number_format((float) $order->amount, 2, '.', '');
    }

    private function splitName(string $name): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        $pos = mb_strrpos($name, ' ');

        return $pos === false
            ? [mb_substr($name, 0, 100), '']
            : [mb_substr(mb_substr($name, 0, $pos), 0, 100), mb_substr(mb_substr($name, $pos + 1), 0, 100)];
    }

    /** Result the local test payment page recorded (APPROVED / DECLINED / CANCELLED). */
    private function testResult(Order $order): array
    {
        $choice = data_get($order->gateway_payload, 'test_result');
        $status = match ($choice) {
            'APPROVED' => 'paid',
            'DECLINED' => 'failed',
            'CANCELLED' => 'cancelled',
            default => 'pending',
        };

        return $this->result($status, $choice ?: 'NOT_FOUND', ['test_gateway' => true, 'test_result' => $choice],
            $status === 'paid' ? (float) $order->amount : null, $order->currency, $status === 'paid' ? 'TEST'.random_int(1000, 9999) : null);
    }

    private function result(string $status, ?string $gatewayStatus, array $payload, mixed $amount = null, ?string $currency = null, ?string $apv = null): array
    {
        return [
            'status' => $status,
            'gateway_status' => $gatewayStatus,
            'amount' => $amount !== null ? (float) $amount : null,
            'currency' => $currency,
            'apv' => $apv,
            'payload' => $payload,
        ];
    }
}
