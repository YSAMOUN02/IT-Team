<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A question and its answers. How an answer is kept depends on the type:
 *  single, multiple, true_false - list of chosen option ids
 *  short    - the text typed (at most 255 characters); a teacher marks it right or wrong (QuizAttempt::mark)
 *  matching - [left option id => option id whose right side was picked]; option text = left, match_text = right
 *  ordering - option ids in the order the student put them; the options' position is the right order
 *  dragdrop - [blank number => option id]; the prompt has "___" for each blank, an option's slot is its blank
 * Every type is all-or-nothing.
 */
class QuizQuestion extends Model
{
    public const TYPES = [
        'single' => 'One correct answer',
        'multiple' => 'Several correct answers',
        'true_false' => 'True / False',
        'short' => 'Short answer',
        'matching' => 'Matching',
        'ordering' => 'Ordering',
        'dragdrop' => 'Drag & drop',
    ];

    public const CHOICE_TYPES = ['single', 'multiple', 'true_false'];

    /** Three or more underscores in a drag & drop prompt mark a blank. */
    public const BLANK = '/_{3,}/u';

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

    /** Short answers have no answer key: a teacher reads and marks them. */
    public function isMarkedByTeacher(): bool
    {
        return $this->type === 'short';
    }

    public function isChoice(): bool
    {
        return in_array($this->type, self::CHOICE_TYPES, true);
    }

    /** @return list<int> */
    public function correctOptionIds(): array
    {
        return $this->options->where('is_correct', true)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
    }

    /** Drag & drop: how many blanks the prompt has. */
    public function blankCount(): int
    {
        return preg_match_all(self::BLANK, (string) $this->prompt);
    }

    /** Drag & drop: the prompt cut at its blanks (one more piece than blanks). */
    public function promptPieces(): array
    {
        return preg_split(self::BLANK, (string) $this->prompt);
    }

    /** Text compared without caring about capitals, extra spaces or the invisible spaces Khmer typing adds. */
    public static function sameText(?string $a, ?string $b): bool
    {
        $clean = fn ($s) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', str_replace(["\u{200B}", "\u{200C}", "\u{200D}", "\u{FEFF}"], '', (string) $s))));

        return $clean($a) !== '' && $clean($a) === $clean($b);
    }

    /** What the form sent, kept in the shape for this type: only real option ids, nothing else. */
    public function normalizeAnswer(mixed $raw): mixed
    {
        $ids = $this->options->pluck('id')->map(fn ($id) => (int) $id)->all();
        $isId = fn ($id) => in_array((int) $id, $ids, true);

        switch ($this->type) {
            case 'short':
                return mb_substr(trim((string) (is_array($raw) ? ($raw['text'] ?? '') : $raw)), 0, 255);
            case 'matching':
                $out = [];
                foreach ((array) $raw as $left => $right) {
                    if ($isId($left) && $isId($right)) {
                        $out[(int) $left] = (int) $right;
                    }
                }

                return $out;
            case 'ordering':
                $order = array_values(array_unique(array_map('intval', array_filter((array) $raw, $isId))));

                return count($order) === count($ids) ? $order : [];
            case 'dragdrop':
                $out = [];
                $blanks = $this->blankCount();
                foreach ((array) $raw as $slot => $id) {
                    if ((int) $slot >= 1 && (int) $slot <= $blanks && $isId($id) && ! in_array((int) $id, $out, true)) {
                        $out[(int) $slot] = (int) $id;
                    }
                }
                ksort($out);

                return $out;
            default:
                return array_values(array_intersect(array_map('intval', (array) $raw), $ids));
        }
    }

    public function hasAnswer(mixed $answer): bool
    {
        return is_array($answer) ? $answer !== [] : trim((string) $answer) !== '';
    }

    /** All-or-nothing, for every type. */
    public function isAnsweredCorrectly(mixed $answer): bool
    {
        if ($this->isMarkedByTeacher()) {
            return false; // only the teacher's mark makes it right (QuizAttempt::isRight)
        }
        if ($this->isChoice()) {
            $chosen = array_values(array_unique(array_map('intval', (array) $answer)));
            sort($chosen);

            return $chosen !== [] && $chosen === $this->correctOptionIds();
        }

        return $this->hasAnswer($answer) && collect($this->reviewRows($answer))->every(fn ($row) => $row['right']);
    }

    /**
     * Part by part, for the review and the practice check:
     * [key, label, given (the student's), correct, right]. Not used for the choice types.
     *
     * @return list<array{key: string, label: ?string, given: ?string, correct: string, right: bool}>
     */
    public function reviewRows(mixed $answer): array
    {
        $options = $this->options->values();
        $byId = $options->keyBy(fn ($o) => (int) $o->id);

        switch ($this->type) {
            case 'matching':
                $answer = (array) $answer;

                return $options->map(fn ($left) => [
                    'key' => (string) $left->id, 'label' => $left->text,
                    'given' => isset($answer[$left->id]) ? $byId->get((int) $answer[$left->id])?->match_text : null,
                    'correct' => (string) $left->match_text,
                    // Two pairs may share a right side: compare the text picked, not the id.
                    'right' => isset($answer[$left->id]) && self::sameText($byId->get((int) $answer[$left->id])?->match_text, $left->match_text),
                ])->all();
            case 'ordering':
                $given = array_values((array) $answer);

                return $options->map(fn ($option, $i) => [
                    'key' => (string) $i, 'label' => (string) ($i + 1),
                    'given' => isset($given[$i]) ? $byId->get((int) $given[$i])?->text : null,
                    'correct' => $option->text,
                    'right' => isset($given[$i]) && (int) $given[$i] === (int) $option->id,
                ])->all();
            case 'dragdrop':
                $answer = (array) $answer;
                $rows = [];
                for ($slot = 1; $slot <= $this->blankCount(); $slot++) {
                    $want = $options->first(fn ($o) => (int) $o->slot === $slot);
                    $put = isset($answer[$slot]) ? $byId->get((int) $answer[$slot]) : null;
                    // The same word may be offered twice: the word counts, not which copy.
                    $rows[] = ['key' => (string) $slot, 'label' => (string) $slot, 'given' => $put?->text, 'correct' => (string) $want?->text, 'right' => $put && $want && self::sameText($put->text, $want->text)];
                }

                return $rows;
            default:
                return [];
        }
    }

    /** The right answer in words, e.g. for the practice check. */
    public function solutionText(): string
    {
        return match ($this->type) {
            'short' => '',
            'matching' => $this->options->map(fn ($o) => $o->text.' → '.$o->match_text)->implode(' · '),
            'ordering' => $this->options->pluck('text')->implode(' → '),
            'dragdrop' => collect($this->reviewRows([]))->pluck('correct')->implode(' · '),
            default => $this->options->where('is_correct', true)->pluck('text')->implode(' · '),
        };
    }

    /** A steady shuffle for one attempt (the same after a reload), never left in the right order. */
    public function shuffledFor(int $attemptId, iterable $items, string $salt = ''): array
    {
        $items = collect($items)->values();
        $shuffled = $items->sortBy(fn ($item) => crc32($attemptId.'-'.$this->id.'-'.$salt.'-'.$item->id))->values();
        if ($items->count() > 1 && $shuffled->pluck('id')->all() === $items->pluck('id')->all()) {
            $shuffled = $shuffled->reverse()->values();
        }

        return $shuffled->all();
    }
}
