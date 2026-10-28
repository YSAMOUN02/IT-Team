<?php

namespace App\Models;

use App\Models\Concerns\HasMediaUrls;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Article extends Model
{
    use HasMediaUrls;

    // News and blog are one section ("news"); old blog links redirect there.
    public const TYPES = [
        'tutorial' => 'Tutorials',
        'news' => 'News',
    ];

    /** One item of each type, for admin headings ("New news item"). */
    public const SINGULAR = [
        'tutorial' => 'tutorial',
        'news' => 'news item',
    ];

    /** Public section of each type: route name prefix and lang key (site.tutorials, ...). */
    public const SECTIONS = [
        'tutorial' => 'tutorials',
        'news' => 'news',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    public static function section(string $type): string
    {
        return self::SECTIONS[$type] ?? 'tutorials';
    }

    public function url(): string
    {
        return route(self::section($this->type).'.show', $this->slug);
    }

    public function indexUrl(): string
    {
        return route(self::section($this->type).'.index');
    }

    public function excerptText(int $limit = 160): string
    {
        $source = $this->excerpt ?: $this->body;

        return Str::limit(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $source)))), $limit);
    }
}
