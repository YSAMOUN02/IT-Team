<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** A newsletter sign-up from the website footer. */
class Subscriber extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'unsubscribed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (Subscriber $s) => $s->token ??= Str::random(40));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('unsubscribed_at');
    }

    public function unsubscribeUrl(): string
    {
        return route('newsletter.unsubscribe', $this->token);
    }
}
