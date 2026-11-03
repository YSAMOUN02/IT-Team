<?php

namespace App\Payments;

/**
 * The admin's switch for online payment (Admin > Settings > Payments). While it is off, nobody can
 * start a PayWay or KHQR payment: the checkout page asks students to get in touch instead, and an
 * admin gives the course by hand. Off until an admin turns it on.
 */
class OnlinePayment
{
    public static function enabled(): bool
    {
        return (string) setting('online_payment', config('payway.online_by_default') ? '1' : '0') === '1';
    }

    /** Where to ask about buying: the setting, else the Telegram link, else the contact page. */
    public static function contactUrl(): string
    {
        $socials = collect(json_decode((string) setting('social_links'), true) ?: []);

        return trim((string) setting('payment_contact_url'))
            ?: (trim((string) setting('telegram_url'))
            ?: ($socials->firstWhere('platform', 'telegram')['url'] ?? route('contact')));
    }

    public static function contactIsTelegram(): bool
    {
        return str_contains(self::contactUrl(), 't.me/') || str_contains(self::contactUrl(), 'telegram.');
    }
}
