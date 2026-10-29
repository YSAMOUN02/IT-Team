<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A test inside a course. Types follow the Cisco NetAcad pattern:
 *  practice - check each answer straight away, unlimited tries, not graded
 *  quiz     - module quiz, must be passed to complete the course
 *  mock     - timed practice exam to prepare for the final, unlimited tries
 *  final    - timed final exam, must be passed for the certificate
 */
class Quiz extends Model
{
    public const TYPES = [
        'practice' => 'Practice',
        'quiz' => 'Module quiz',
        'mock' => 'Mock exam',
        'final' => 'Final exam',
    ];

    /** Types that must be passed before the course counts as completed. */
    public const REQUIRED_TYPES = ['quiz', 'final'];

    protected $guarded = ['id'];

    protected $casts = [
        'is_preview' => 'boolean',
        'is_published' => 'boolean',
        'shuffle_questions' => 'boolean',
        'show_answers' => 'boolean',
        'time_limit' => 'integer',
        'pass_percent' => 'integer',
        'max_attempts' => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'course_module_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position')->orderBy('id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function isPractice(): bool
    {
        return $this->type === 'practice';
    }

    public function isRequired(): bool
    {
        return in_array($this->type, self::REQUIRED_TYPES, true);
    }

    public function typeLabel(): string
    {
        return __('account.quiz_type_'.$this->type);
    }

    public function url(): string
    {
        return route('learn.quiz', ['course' => $this->course->slug, 'quiz' => $this->slug]);
    }

    /** Attempts left for a user, null when unlimited. Extra tries a teacher approved count too. */
    public function attemptsLeft(User $user): ?int
    {
        if (! $this->max_attempts) {
            return null;
        }
        $allowed = $this->max_attempts + (int) $this->retakeRequests()->where('user_id', $user->id)->where('status', 'approved')->sum('extra_attempts');

        return max(0, $allowed - $this->attempts()->where('user_id', $user->id)->whereNotNull('submitted_at')->count());
    }

    public function retakeRequests(): HasMany
    {
        return $this->hasMany(RetakeRequest::class);
    }

    /** Out of tries without passing, and not already waiting for the teacher's answer. */
    public function canRequestRetake(User $user): bool
    {
        return ! $this->isPractice()
            && $this->attemptsLeft($user) === 0
            && ! $this->attempts()->where('user_id', $user->id)->where('passed', true)->exists()
            && ! $this->retakeRequests()->where('user_id', $user->id)->pending()->exists();
    }

    public function bestAttempt(User $user): ?QuizAttempt
    {
        return $this->attempts()->where('user_id', $user->id)->whereNotNull('submitted_at')
            ->orderByDesc('percent')->orderByDesc('submitted_at')->first();
    }

    public function passedBy(User $user): bool
    {
        return $this->attempts()->where('user_id', $user->id)->where('passed', true)->exists();
    }
}
