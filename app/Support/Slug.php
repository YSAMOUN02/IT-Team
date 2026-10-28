<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Slug
{
    /**
     * Like Str::slug(), but keeps Khmer (and other non-Latin) letters, which
     * Str::slug() would strip, so Khmer titles still give readable URLs.
     */
    public static function make(string $text): string
    {
        $text = Str::lower(trim($text));
        $text = preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '-', $text);

        return trim(preg_replace('/-+/u', '-', $text), '-');
    }

    /** Make a slug that is unique within the given query (excluding $ignoreId). */
    public static function unique(string $text, Builder $query, ?int $ignoreId = null, string $column = 'slug'): string
    {
        $base = static::make($text) ?: 'item';
        $slug = $base;
        $i = 2;
        while ((clone $query)->where($column, $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
