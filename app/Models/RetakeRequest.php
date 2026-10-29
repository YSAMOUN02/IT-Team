<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "May I try again?" A student who used every try at a test without passing asks; the teacher
 * (an admin) approves with extra tries or declines, with an optional note. Approved extra tries
 * add to the test's max attempts for that student (Quiz::attemptsLeft).
 */
class RetakeRequest extends Model
{
    public const STATUSES = ['pending' => 'Waiting', 'approved' => 'Approved', 'declined' => 'Declined'];

    protected $guarded = ['id'];

    protected $casts = [
        'extra_attempts' => 'integer',
        'decided_at' => 'datetime',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /** Approve (with extra tries) or decline, by the admin who decided. */
    public function decide(User $teacher, bool $approve, int $extraAttempts = 1, ?string $reply = null): self
    {
        $this->forceFill([
            'status' => $approve ? 'approved' : 'declined',
            'extra_attempts' => $approve ? max(1, $extraAttempts) : 0,
            'reply' => filled($reply) ? trim($reply) : null,
            'decided_by' => $teacher->id,
            'decided_at' => now(),
        ])->save();

        return $this;
    }
}
