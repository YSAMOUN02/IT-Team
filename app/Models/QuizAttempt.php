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
     * Grade and close the attempt. $answers = [question_id => [option ids]].
     * Unanswered questions score zero.
     */
    public function grade(array $answers): self
    {
        $questions = $this->orderedQuestions();
        $clean = [];
        $score = 0;
        $max = 0;

        foreach ($questions as $question) {
            $chosen = array_map('intval', (array) ($answers[$question->id] ?? []));
            $valid = $question->options->pluck('id')->map(fn ($id) => (int) $id)->all();
            $chosen = array_values(array_intersect($chosen, $valid));
            $clean[$question->id] = $chosen;
            $max += $question->points;
            if ($question->isAnsweredCorrectly($chosen)) {
                $score += $question->points;
            }
        }

        $percent = $max ? (int) floor($score * 100 / $max) : 0;

        $this->fill([
            'answers' => $clean,
            'score' => $score,
            'max_score' => $max,
            'percent' => $percent,
            'passed' => $max > 0 && $percent >= $this->quiz->pass_percent,
            'submitted_at' => now(),
        ])->save();

        return $this;
    }
}
