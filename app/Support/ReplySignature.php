<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The one signature under every reply sent from Admin > Messages. Editable on the reply form
 * (a change is kept for the next replies) and in Admin > Settings. Until someone writes one,
 * it is made from the site's name, phone, email and address.
 */
class ReplySignature
{
    public static function get(): string
    {
        // Saved from Settings it has the browser's Windows line ends; the reply form compares without them.
        $saved = trim(str_replace("\r\n", "\n", (string) setting('reply_signature')));

        return $saved !== '' ? $saved : self::default();
    }

    public static function default(): string
    {
        return implode("\n", array_filter([
            setting('site_name', config('app.name')),
            setting('phone') ? 'Tel: '.setting('phone') : null,
            setting('email'),
            rtrim((string) config('app.public_url'), '/') ?: null,
        ]));
    }

    public static function put(?string $signature): void
    {
        Setting::put(['reply_signature' => trim(str_replace("\r\n", "\n", (string) $signature))]);
    }
}
