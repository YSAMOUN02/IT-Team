<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class QuizAttempt extends Model
{
    /** Seconds of slack after the timer ends (slow connections, auto-submit). */
    public const GRACE_SECONDS = 30;

    protected $guarded = ['id'];

    protected $casts = [
        'question_order' => 'array',
        'answers' => 'array',
        'marks' => 'array',
        'needs_marking' => 'boolean',
        'passed' => 'boolean',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    /** One decimal at most (a teacher can give part of the points): 4.0 comes back as 4, 1.4 as 1.4. */
    protected function score(): Attribute
    {
        return Attribute::get(function ($value) {
            $value = round((float) $value, 1);

            return floor($value) === $value ? (int) $value : $value;
        });
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function deadline(): ?Carbon
    {
        return $this->quiz->time_limit ? $this->started_at->copy()->addMinutes($this->quiz->time_limit) : null;
    }

    public function isOverdue(): bool
    {
        $deadline = $this->deadline();

        return $deadline !== null && now()->greaterThan($deadline->copy()->addSeconds(self::GRACE_SECONDS));
    }

    /** Questions in the order this attempt shows them. */
    public function orderedQuestions()
    {
        $questions = $this->quiz->questions()->with('options')->get()->keyBy('id');
        $order = $this->question_order ?: $questions->keys()->all();

        return collect($order)->map(fn ($id) => $questions->get($id))->filter()->values();
    }

    /**
     * Grade and close the attempt. $answers = [question_id => answer]; the answer's shape depends on
     * the question type (see QuizQuestion). Unanswered questions score zero; short answers wait for a teacher.
     */
    public function grade(array $answers): self
    {
        $clean = [];
        foreach ($this->orderedQuestions() as $question) {
            $clean[$question->id] = $question->normalizeAnswer($answers[$question->id] ?? null);
        }
        $this->answers = $clean;
        $this->submitted_at = now();

        return $this->rescore();
    }

    /**
     * A written answer's mark in percent (0-100), null while nobody has marked it. An empty answer is 0.
     * Marks saved as true / false (before marks went in steps of 10%) count as 100 / 0.
     */
    public function markPercent(QuizQuestion $question): ?int
    {
        if (! $question->hasAnswer(($this->answers ?? [])[$question->id] ?? null)) {
            return 0;
        }
        $mark = ($this->marks ?? [])[$question->id] ?? null;

        return match (true) {
            $mark === null => null,
            is_bool($mark) => $mark ? 100 : 0,
            default => max(0, min(100, (int) $mark)),
        };
    }

    /** Points earned on one question: all or nothing, or a teacher's percent of the points for a written answer. */
    public function pointsFor(QuizQuestion $question): float
    {
        if ($question->isMarkedByTeacher()) {
            return round($question->points * ($this->markPercent($question) ?? 0) / 100, 1);
        }

        return $this->isRight($question) ? (float) $question->points : 0.0;
    }

    /** Right (full points) or not; for a written answer null while nobody has marked it. */
    public function isRight(QuizQuestion $question): ?bool
    {
        if ($question->isMarkedByTeacher()) {
            $percent = $this->markPercent($question);

            return $percent === null ? null : $percent === 100;
        }

        return $question->isAnsweredCorrectly(($this->answers ?? [])[$question->id] ?? null);
    }

    /** A teacher gives one written answer 0-100% (steps of 10) of its points; the score and result follow. */
    public function mark(QuizQuestion $question, int $percent): self
    {
        $marks = $this->marks ?? [];
        $marks[$question->id] = max(0, min(100, $percent));
        $this->marks = $marks;

        return $this->rescore();
    }

    /** Score, percent and result from the answers and marks. Not passed while anything waits to be marked. */
    public function rescore(): self
    {
        $score = 0.0;
        $max = 0;
        $waiting = false;
        foreach ($this->orderedQuestions() as $question) {
            $max += $question->points;
            $right = $this->isRight($question);
            $waiting = $waiting || $right === null;
            $score += $this->pointsFor($question);
        }
        $score = round($score, 1);
        $percent = $max ? (int) floor(round($score * 100 / $max, 6)) : 0;

        $this->fill([
            'score' => $score,
            'max_score' => $max,
            'percent' => $percent,
            'needs_marking' => $waiting,
            'passed' => ! $waiting && $max > 0 && $percent >= $this->quiz->pass_percent,
        ])->save();

        return $this;
    }
}
