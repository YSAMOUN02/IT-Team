<?php

namespace App\Models;

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

    /** Right or wrong; for a short answer the teacher's mark, null while nobody has marked it (an empty one is wrong). */
    public function isRight(QuizQuestion $question): ?bool
    {
        $answer = ($this->answers ?? [])[$question->id] ?? null;
        if ($question->isMarkedByTeacher()) {
            if (! $question->hasAnswer($answer)) {
                return false;
            }
            $mark = ($this->marks ?? [])[$question->id] ?? null;

            return $mark === null ? null : (bool) $mark;
        }

        return $question->isAnsweredCorrectly($answer);
    }

    /** A teacher marks one short answer right or wrong; the score and result follow. */
    public function mark(QuizQuestion $question, bool $right): self
    {
        $marks = $this->marks ?? [];
        $marks[$question->id] = $right;
        $this->marks = $marks;

        return $this->rescore();
    }

    /** Score, percent and result from the answers and marks. Not passed while anything waits to be marked. */
    public function rescore(): self
    {
        $score = 0;
        $max = 0;
        $waiting = false;
        foreach ($this->orderedQuestions() as $question) {
            $max += $question->points;
            $right = $this->isRight($question);
            $waiting = $waiting || $right === null;
            $score += $right ? $question->points : 0;
        }
        $percent = $max ? (int) floor($score * 100 / $max) : 0;

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
