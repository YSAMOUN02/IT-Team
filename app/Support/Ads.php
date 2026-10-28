<?php

namespace App\Support;

/**
 * Ad output: your own banner image or Google AdSense, per placement. Everything
 * returns null/empty unless it is fully set up, so an unused placement leaves no
 * trace on the page.
 */
class Ads
{
    public const TYPES = [
        'image' => 'Your own image',
        'google' => 'Google AdSense',
    ];

    public const FORMATS = [
        'auto' => 'Responsive (recommended)',
        'horizontal' => 'Wide banner',
        'rectangle' => 'Rectangle',
        'in-article' => 'In-article (blends into text)',
    ];

    /** "ca-pub-1234567890123456", or null when not set / invalid. */
    public static function publisherId(): ?string
    {
        $id = trim((string) setting('ads_publisher_id'));
        if (preg_match('/^(?:ca-)?pub-(\d{10,20})$/', $id, $m)) {
            return 'ca-pub-'.$m[1];
        }

        return null;
    }

    public static function autoAds(): bool
    {
        return self::publisherId() !== null && (bool) setting('ads_auto');
    }

    /** 'image' or 'google'. Spots saved before there was a choice keep Google if they have an ad unit. */
    public static function type(string $placement): string
    {
        $type = setting("ad_{$placement}_type");
        if (array_key_exists((string) $type, self::TYPES)) {
            return $type;
        }

        return filled(setting("ad_{$placement}_slot")) || filled(setting("ad_{$placement}_code")) ? 'google' : 'image';
    }

    /** Is this placement switched on and complete? */
    public static function isLive(string $placement): bool
    {
        return self::type($placement) === 'image'
            ? self::banner($placement) !== null
            : self::slotHtml($placement) !== null;
    }

    /**
     * Your own banner for a placement, or null when it should not appear.
     *
     * @return array{image: string, phone: ?string, width: ?int, height: ?int, phone_width: ?int, phone_height: ?int, alt: string, link: ?string, external: bool}|null
     */
    public static function banner(string $placement): ?array
    {
        if (! array_key_exists($placement, config('ads')) || ! setting("ad_{$placement}_enabled") || self::type($placement) !== 'image') {
            return null;
        }
        $image = media_url(setting("ad_{$placement}_image"));
        if (! $image) {
            return null;
        }

        $link = trim((string) setting("ad_{$placement}_link"));
        $phone = media_url(setting("ad_{$placement}_image_phone"));
        [$width, $height] = self::dimensions(setting("ad_{$placement}_image_size"));
        [$phoneWidth, $phoneHeight] = self::dimensions($phone ? setting("ad_{$placement}_image_phone_size") : null);

        return [
            'image' => $image,
            'phone' => $phone,
            'width' => $width,
            'height' => $height,
            'phone_width' => $phoneWidth,
            'phone_height' => $phoneHeight,
            'alt' => trim((string) setting("ad_{$placement}_alt")),
            'link' => $link !== '' ? $link : null,
            'external' => $link !== '' && ! self::isOwnLink($link),
        ];
    }

    /** A link to this website (opens in the same tab) rather than to another site. */
    public static function isOwnLink(string $link): bool
    {
        if (str_starts_with($link, '/') && ! str_starts_with($link, '//')) {
            return true;
        }
        $host = parse_url($link, PHP_URL_HOST);

        return $host && in_array(strtolower($host), array_filter([request()->getHost(), parse_url((string) config('app.url'), PHP_URL_HOST)]), true);
    }

    /** "1200x200" -> [1200, 200], so the page keeps the banner's space while it loads. */
    private static function dimensions(?string $size): array
    {
        return $size && preg_match('/^(\d{1,5})x(\d{1,5})$/', $size, $m) ? [(int) $m[1], (int) $m[2]] : [null, null];
    }

    /**
     * 'off'         - switched off: nothing is output at all
     * 'placeholder' - switched on but no ad unit yet: an "ad space" box
     * 'live'        - switched on with an ad unit: the real ad
     */
    public static function state(string $placement): string
    {
        if (! array_key_exists($placement, config('ads')) || ! setting("ad_{$placement}_enabled")) {
            return 'off';
        }

        return self::isLive($placement) ? 'live' : 'placeholder';
    }

    /** Box size the placeholder mimics, from the placement's format. */
    public static function format(string $placement): string
    {
        return setting("ad_{$placement}_format", config("ads.$placement.format", 'auto'));
    }

    /** The ad markup for a placement, or null when it should not appear. */
    public static function slotHtml(string $placement): ?string
    {
        if (! array_key_exists($placement, config('ads')) || ! setting("ad_{$placement}_enabled") || self::type($placement) !== 'google') {
            return null;
        }

        $custom = trim((string) setting("ad_{$placement}_code"));
        if ($custom !== '') {
            return $custom;
        }

        $publisher = self::publisherId();
        $slot = trim((string) setting("ad_{$placement}_slot"));
        if (! $publisher || ! preg_match('/^\d{6,20}$/', $slot)) {
            return null;
        }

        $format = setting("ad_{$placement}_format", config("ads.$placement.format", 'auto'));
        $attrs = match ($format) {
            'in-article' => 'style="display:block;text-align:center" data-ad-layout="in-article" data-ad-format="fluid"',
            'horizontal' => 'style="display:block" data-ad-format="horizontal" data-full-width-responsive="true"',
            'rectangle' => 'style="display:inline-block;width:336px;height:280px"',
            default => 'style="display:block" data-ad-format="auto" data-full-width-responsive="true"',
        };

        return '<ins class="adsbygoogle" '.$attrs.' data-ad-client="'.e($publisher).'" data-ad-slot="'.e($slot).'"></ins>'
            .'<script>(adsbygoogle = window.adsbygoogle || []).push({});</script>';
    }

    /** Loader script for the <head>, only when some ad will actually be requested. */
    public static function headScript(): ?string
    {
        $publisher = self::publisherId();
        if (! $publisher) {
            return null;
        }

        $needsLoader = self::autoAds() || collect(array_keys(config('ads')))->contains(
            fn ($key) => trim((string) setting("ad_{$key}_code")) === '' && self::slotHtml($key) !== null
        );

        return $needsLoader
            ? '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client='.e($publisher).'" crossorigin="anonymous"></script>'
            : null;
    }

    /** Lines for /ads.txt, or null when there is no publisher ID. */
    public static function adsTxt(): ?string
    {
        $publisher = self::publisherId();
        if (! $publisher) {
            return null;
        }

        $lines = ['google.com, '.substr($publisher, 3).', DIRECT, f08c47fec0942fa0'];
        $extra = trim((string) setting('ads_txt_extra'));

        return implode("\n", $extra !== '' ? [...$lines, $extra] : $lines)."\n";
    }
}
