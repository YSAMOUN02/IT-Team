<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One way to buy a course: an access period, its price and an optional discount. */
class CoursePlan extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'price' => 'decimal:2',
        'days' => 'integer',
        'discount_percent' => 'integer',
        'discount_starts_at' => 'date',
        'discount_ends_at' => 'date',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** The discount applies today (no dates = always). */
    public function discountActive(): bool
    {
        return $this->discount_percent > 0
            && (! $this->discount_starts_at || $this->discount_starts_at->startOfDay()->lte(now()))
            && (! $this->discount_ends_at || $this->discount_ends_at->endOfDay()->gte(now()));
    }

    /** What a student pays today. */
    public function currentPrice(): float
    {
        $price = (float) $this->price;
        if ($this->discountActive()) {
            $price *= (100 - $this->discount_percent) / 100;
        }

        return round($price, $this->currency() === 'KHR' ? 0 : 2);
    }

    public function currency(): string
    {
        return $this->course?->currency ?: 'USD';
    }

    public function formattedPrice(): string
    {
        return money_format_simple($this->currentPrice(), $this->currency());
    }

    public function formattedOriginalPrice(): string
    {
        return money_format_simple((float) $this->price, $this->currency());
    }

    /** "1 month", "1 year", "Lifetime access", or the admin's own label. */
    public function periodLabel(): string
    {
        return $this->label ?: Course::accessLabelFor($this->days);
    }
}
