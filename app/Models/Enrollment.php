<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'disabled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Started, not ended and not turned off by an admin. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('starts_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereNull('disabled_at');
    }

    public function isActive(): bool
    {
        return $this->inPeriod() && $this->disabled_at === null;
    }

    /** Inside the paid period, whether or not an admin turned it off. */
    public function inPeriod(): bool
    {
        return $this->starts_at <= now() && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Give a user access to a course (or extend it). Renewing while access is
     * still running adds the new period after the current end date.
     * Access an admin turned off stays off until an admin gives it again by hand.
     */
    public static function grant(User $user, Course $course, ?int $days, string $source = 'payment', ?Order $order = null): self
    {
        $enrollment = static::firstOrNew(['user_id' => $user->id, 'course_id' => $course->id]);
        $from = $enrollment->exists && $enrollment->inPeriod() && $enrollment->expires_at ? $enrollment->expires_at : now();

        $enrollment->fill([
            'starts_at' => $enrollment->exists && $enrollment->inPeriod() ? $enrollment->starts_at : now(),
            'expires_at' => $days ? $from->copy()->addDays($days) : null,
            'source' => $source,
            'order_id' => $order?->id ?? $enrollment->order_id,
        ]);
        if ($source === 'manual') {
            $enrollment->disabled_at = null;
        }
        $enrollment->save();

        return $enrollment;
    }

    /**
     * Take back the access a refunded order paid for. Access bought with the
     * student's other paid orders is kept (renewals are replayed in order).
     * Access an admin gave by hand is left alone. Returns whether anything changed.
     */
    public static function revokeOrder(Order $order): bool
    {
        $enrollment = static::where('user_id', $order->user_id)->where('course_id', $order->course_id)->first();
        if (! $enrollment || $enrollment->source !== 'payment' || ! $enrollment->inPeriod()) {
            return false;
        }

        $end = now();
        $lastOrder = null;
        $others = Order::where('user_id', $order->user_id)->where('course_id', $order->course_id)
            ->where('status', 'paid')->whereKeyNot($order->id)->orderBy('paid_at')->get();

        foreach ($others as $other) {
            if (! $other->access_days) {
                $end = null; // another lifetime purchase
                $lastOrder = $other;

                break;
            }
            $paidAt = $other->paid_at ?? $other->created_at;
            $from = $lastOrder && $end->gt($paidAt) ? $end : $paidAt;
            $end = $from->copy()->addDays($other->access_days);
            $lastOrder = $other;
        }

        $enrollment->forceFill([
            'expires_at' => $end === null ? null : ($end->isFuture() ? $end : now()),
            'order_id' => $lastOrder?->id ?? $enrollment->order_id,
        ])->save();

        return true;
    }
}
