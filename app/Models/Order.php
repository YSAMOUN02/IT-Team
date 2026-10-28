<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Order extends Model
{
    public const STATUSES = [
        'pending' => 'Pending',
        'paid' => 'Paid',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
        'review' => 'Needs review',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'discount_percent' => 'integer',
        'gateway_payload' => 'array',
        'paid_at' => 'datetime',
        'checked_at' => 'datetime',
        'access_days' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function enrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class);
    }

    /** A PayWay tran_id: 20 characters at most, unique. */
    public static function newNumber(): string
    {
        do {
            $number = 'T'.now()->format('ymdHis').Str::upper(Str::random(5));
        } while (static::where('number', $number)->exists());

        return $number;
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function formattedAmount(): string
    {
        return money_format_simple((float) $this->amount, $this->currency);
    }
}
