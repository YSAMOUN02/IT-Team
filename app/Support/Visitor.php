<?php

namespace App\Support;

/**
 * Reads a visitor's browser line (user agent) and where they came from, for the
 * website statistics. Simple rules, no outside services.
 */
class Visitor
{
    public static function isBot(?string $agent): bool
    {
        return $agent === null || $agent === ''
            || (bool) preg_match('/bot|crawl|spider|slurp|preview|headless|lighthouse|pingdom|monitor|curl|wget|python|httpclient|facebookexternalhit|embedly|whatsapp|telegrambot/i', $agent);
    }

    public static function device(string $agent): string
    {
        return match (true) {
            (bool) preg_match('/iPad|Tablet|PlayBook|Silk|(Android(?!.*Mobile))/i', $agent) => 'Tablet',
            (bool) preg_match('/Mobi|iPhone|iPod|Android.*Mobile|Windows Phone/i', $agent) => 'Mobile',
            default => 'Desktop',
        };
    }

    public static function browser(string $agent): string
    {
        return match (true) {
            (bool) preg_match('/FBAN|FBAV|FB_IAB/i', $agent) => 'Facebook app',
            (bool) preg_match('/Telegram/i', $agent) => 'Telegram app',
            (bool) preg_match('/Instagram/i', $agent) => 'Instagram app',
            (bool) preg_match('/TikTok|musical_ly|Bytedance/i', $agent) => 'TikTok app',
            (bool) preg_match('/Edg\//i', $agent) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/i', $agent) => 'Opera',
            (bool) preg_match('/SamsungBrowser/i', $agent) => 'Samsung Internet',
            (bool) preg_match('/Firefox|FxiOS/i', $agent) => 'Firefox',
            (bool) preg_match('/Chrome|CriOS/i', $agent) => 'Chrome',
            (bool) preg_match('/Safari/i', $agent) => 'Safari',
            default => 'Other',
        };
    }

    public static function os(string $agent): string
    {
        return match (true) {
            (bool) preg_match('/Android/i', $agent) => 'Android',
            (bool) preg_match('/iPhone|iPad|iPod/i', $agent) => 'iOS',
            (bool) preg_match('/Windows/i', $agent) => 'Windows',
            (bool) preg_match('/Mac OS X|Macintosh/i', $agent) => 'macOS',
            (bool) preg_match('/CrOS/i', $agent) => 'ChromeOS',
            (bool) preg_match('/Linux/i', $agent) => 'Linux',
            default => 'Other',
        };
    }

    /**
     * Where the visit came from: a utm_source in the link, else the referring site
     * (Facebook, Google, TikTok…), else "Direct" (typed, bookmark, app).
     *
     * @param  list<string>  $ownHosts  the addresses this site answers on
     * @return array{0: string, 1: ?string} [source, referrer host]
     */
    public static function source(?string $referrer, ?string $utm, array $ownHosts): array
    {
        $clean = fn (?string $host) => $host ? preg_replace('/^(www|m|l|lm|mobile)\./', '', strtolower($host)) : null;
        $host = $clean($referrer ? (string) parse_url($referrer, PHP_URL_HOST) : null);
        if ($host === null || $host === '' || in_array($host, array_map($clean, $ownHosts), true)) {
            $host = null; // moving around inside the site
        }

        if ($utm) {
            return [mb_substr(ucfirst(strtolower(trim($utm))), 0, 40), $host];
        }

        $known = [
            'facebook.com' => 'Facebook', 'fb.com' => 'Facebook', 'fb.me' => 'Facebook', 'messenger.com' => 'Messenger',
            'instagram.com' => 'Instagram', 'tiktok.com' => 'TikTok', 't.me' => 'Telegram', 'telegram.org' => 'Telegram',
            'youtube.com' => 'YouTube', 'youtu.be' => 'YouTube', 'linkedin.com' => 'LinkedIn', 'lnkd.in' => 'LinkedIn',
            'x.com' => 'X (Twitter)', 't.co' => 'X (Twitter)', 'bing.com' => 'Bing', 'duckduckgo.com' => 'DuckDuckGo',
        ];
        if ($host === null) {
            return ['Direct', null];
        }
        if (preg_match('/(^|\.)google\./', $host)) {
            return ['Google', $host];
        }
        foreach ($known as $domain => $name) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return [$name, $host];
            }
        }

        return [mb_substr($host, 0, 40), $host];
    }
}
