<?php

namespace App\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\Http;

/**
 * KHQR (Cambodia's bank QR, run by Bakong): a QR code with the exact amount, paid to our own Bakong
 * account from any bank app. Payments are seen through the Bakong Open API (check by the QR's MD5)
 * when BAKONG_TOKEN is set; otherwise an admin confirms them (Admin > Orders).
 *
 * The text follows the EMVCo merchant-presented QR format with the KHQR tags:
 * 29 = individual Bakong account, 62 = bill number, 99 = created / expires, 63 = CRC16.
 */
class Khqr
{
    public function accountId(): ?string
    {
        return trim((string) setting('khqr_account_id')) ?: null;
    }

    /** Offered at checkout only once the receiving account is filled in. */
    public function available(): bool
    {
        return $this->accountId() !== null;
    }

    /** Payments are checked automatically (Bakong API) rather than confirmed by an admin. */
    public function autoCheck(): bool
    {
        return filled(config('khqr.token'));
    }

    public function minutes(): int
    {
        return max(3, min(120, (int) (setting('khqr_minutes') ?: 15)));
    }

    /**
     * The QR for an order: reused while still valid, otherwise a new one (new time = new MD5).
     *
     * @return array{qr: string, md5: string, expires_at: string}
     */
    public function forOrder(Order $order): array
    {
        $current = data_get($order->gateway_payload, 'khqr');
        if ($current && now()->lt($current['expires_at'])) {
            return $current;
        }

        $created = now();
        $expires = $created->copy()->addMinutes($this->minutes());
        $qr = $this->payload((float) $order->amount, $order->currency, $order->number, $created->getTimestampMs(), $expires->getTimestampMs());
        $khqr = ['qr' => $qr, 'md5' => md5($qr), 'expires_at' => $expires->toIso8601String()];

        // Every QR shown is kept: a student may pay an older one that was still on screen.
        $payload = (array) $order->gateway_payload;
        $payload['khqr'] = $khqr;
        $payload['khqr_md5s'] = array_values(array_unique([...($payload['khqr_md5s'] ?? []), $khqr['md5']]));
        $order->forceFill(['gateway_payload' => $payload])->save();

        return $khqr;
    }

    public function payload(float $amount, string $currency, string $bill, int $createdMs, int $expiresMs): string
    {
        $khr = strtoupper($currency) === 'KHR';
        $name = $this->ascii(setting('khqr_merchant_name') ?: setting('site_name', config('app.name')), 25);
        $city = $this->ascii(setting('khqr_merchant_city') ?: 'Phnom Penh', 15);

        $text = $this->tag('00', '01')                                   // payload format
            .$this->tag('01', '12')                                      // dynamic: one amount, one payment
            .$this->tag('29', $this->tag('00', (string) $this->accountId()))
            .$this->tag('52', '5999')                                    // merchant category: education / other
            .$this->tag('53', $khr ? '116' : '840')
            .$this->tag('54', $khr ? (string) (int) round($amount) : number_format($amount, 2, '.', ''))
            .$this->tag('58', 'KH')
            .$this->tag('59', $name)
            .$this->tag('60', $city)
            .$this->tag('62', $this->tag('01', substr($bill, 0, 25)))
            .$this->tag('99', $this->tag('00', (string) $createdMs).$this->tag('01', (string) $expiresMs))
            .'6304';

        return $text.$this->crc16($text);
    }

    /**
     * Ask Bakong whether any QR shown for this order was paid. Same answer shape as PayWay's
     * check, so OrderProcessor::apply() treats both alike.
     */
    public function check(Order $order): array
    {
        $pending = ['status' => 'pending', 'gateway_status' => 'WAITING', 'amount' => null, 'currency' => null, 'apv' => null, 'payload' => null];
        if (! $this->autoCheck()) {
            return $pending;
        }

        foreach (array_reverse((array) data_get($order->gateway_payload, 'khqr_md5s', [])) as $md5) {
            $response = Http::withToken((string) config('khqr.token'))->acceptJson()->timeout(10)
                ->post(rtrim(config('khqr.api_url'), '/').'/v1/check_transaction_by_md5', ['md5' => $md5]);
            $json = $response->json() ?? [];
            if ((int) ($json['responseCode'] ?? 1) === 0 && ! empty($json['data'])) {
                return [
                    'status' => 'paid',
                    'gateway_status' => 'BAKONG_PAID',
                    'amount' => isset($json['data']['amount']) ? (float) $json['data']['amount'] : null,
                    'currency' => $json['data']['currency'] ?? null,
                    'apv' => substr((string) ($json['data']['hash'] ?? $md5), 0, 40),
                    'payload' => ['md5' => $md5, 'from' => $json['data']['fromAccountId'] ?? null],
                ];
            }
        }

        return $pending;
    }

    private function tag(string $id, string $value): string
    {
        return $id.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    /** Bank apps need plain letters here: Khmer and accents are dropped. */
    private function ascii(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) preg_replace('/[^\x20-\x7E]/', '', \Illuminate\Support\Str::ascii($text))));

        return substr($text !== '' ? $text : 'TEAMIT Cambodia', 0, $max);
    }

    /** CRC-16/CCITT-FALSE, as EMVCo QR codes use. */
    public function crc16(string $data): string
    {
        $crc = 0xFFFF;
        for ($i = 0, $n = strlen($data); $i < $n; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
