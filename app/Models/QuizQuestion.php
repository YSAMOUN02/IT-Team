<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizQuestion extends Model
{
    public const TYPES = [
        'single' => 'One correct answer',
        'multiple' => 'Several correct answers',
        'true_false' => 'True / False',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'points' => 'integer',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuizOption::class)->orderBy('position')->orderBy('id');
    }

    /** @return list<int> */
    public function correctOptionIds(): array
    {
        return $this->options->where('is_correct', true)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
    }

    /** All-or-nothing: the chosen set must equal the correct set. */
    public function isAnsweredCorrectly(array $chosen): bool
    {
        $chosen = array_values(array_unique(array_map('intval', $chosen)));
        sort($chosen);

        return $chosen !== [] && $chosen === $this->correctOptionIds();
    }
}
