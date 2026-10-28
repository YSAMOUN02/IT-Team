<?php

namespace App\Models;

use App\Models\Concerns\HasMediaUrls;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    use HasMediaUrls;

    protected $guarded = ['id'];

    protected $casts = [
        'is_published' => 'boolean',
        'rating' => 'integer',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->orderBy('sort_order')->orderBy('id');
    }
}
