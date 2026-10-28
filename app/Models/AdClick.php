<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A click on your own banner ad. */
class AdClick extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['placement', 'visitor_id'];

    /** Clicks per placement since a moment, e.g. ['header' => 12]. */
    public static function countsSince(\DateTimeInterface $since): array
    {
        return static::where('created_at', '>=', $since)
            ->selectRaw('placement, count(*) as total')->groupBy('placement')
            ->pluck('total', 'placement')->map(fn ($total) => (int) $total)->all();
    }
}
