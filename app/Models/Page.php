<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class Page extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'sections' => 'array',
        'is_published' => 'boolean',
    ];

    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->where('is_published', true)->first();
    }

    /** Read a section field, e.g. $page->field('hero.title'). */
    public function field(string $key, mixed $default = null): mixed
    {
        $value = Arr::get($this->sections ?? [], $key);

        return ($value === null || $value === '' || $value === []) ? $default : $value;
    }

    /** Field definitions for this page's template (config/page_templates.php). */
    public function templateDefinition(): array
    {
        return config('page_templates.'.$this->template, config('page_templates.default'));
    }
}
