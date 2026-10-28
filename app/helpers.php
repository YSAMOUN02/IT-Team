<?php

use App\Models\Setting;
use App\Support\Media;

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        try {
            return Setting::get($key, $default);
        } catch (Throwable) {
            // Settings table not migrated yet.
            return $default;
        }
    }
}

if (! function_exists('media_url')) {
    function media_url(?string $path): ?string
    {
        return Media::url($path);
    }
}

if (! function_exists('social_links')) {
    /**
     * Social accounts from Admin > Settings, in the admin's order:
     * [['platform' => 'telegram', 'url' => …, 'label' => 'Telegram', 'color' => …, 'icon' => '<path…>', 'chat' => bool], …]
     * Before the list is first saved, the old single fields (telegram_url …) are used.
     */
    function social_links(): array
    {
        $saved = setting('social_links');
        $links = $saved !== null
            ? (json_decode((string) $saved, true) ?: [])
            : array_values(array_filter(array_map(
                fn ($platform) => ['platform' => $platform, 'url' => setting($platform.'_url')],
                ['facebook', 'telegram', 'youtube', 'tiktok']
            ), fn ($link) => filled($link['url'])));

        $platforms = config('socials');

        return array_values(array_filter(array_map(function ($link) use ($platforms) {
            $meta = $platforms[$link['platform'] ?? ''] ?? $platforms['website'];

            return filled($link['url'] ?? null) ? [
                'platform' => $link['platform'] ?? 'website',
                'url' => $link['url'],
                'label' => $meta['label'],
                'color' => $meta['color'],
                'icon' => $meta['icon'],
                'chat' => $meta['chat'] ?? false,
            ] : null;
        }, $links)));
    }
}

if (! function_exists('rich_text')) {
    /** Output a field that may hold HTML (from the editor) or plain text with line breaks. */
    function rich_text(?string $value): string
    {
        $value = trim((string) $value);

        return preg_match('/<[a-z][\s\S]*>/i', $value) ? $value : nl2br(e($value), false);
    }
}

if (! function_exists('money_format_simple')) {
    /** "$49.00" for USD, "49,000 ៛" for riel. */
    function money_format_simple(float $amount, string $currency = 'USD'): string
    {
        return strtoupper($currency) === 'KHR'
            ? number_format($amount, 0).' ៛'
            : '$'.number_format($amount, 2);
    }
}

if (! function_exists('link_url')) {
    /** Resolve a link typed in the admin: "/about", "about", "#x" or a full URL. */
    function link_url(?string $link): string
    {
        $link = trim((string) $link);
        if ($link === '') {
            return '#';
        }
        if (preg_match('~^([a-z][a-z0-9+.-]*:|//|#)~i', $link)) {
            return $link;
        }

        return url($link);
    }
}
