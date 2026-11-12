<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Questions and answer options of a quiz. */
class QuestionController extends Controller
{
    use HandlesImageUploads;

    public function create(Request $request, Quiz $quiz)
    {
        $type = array_key_exists((string) $request->query('type'), QuizQuestion::TYPES) ? $request->query('type') : 'single';

        return view('admin.questions.form', [
            'quiz' => $quiz,
            'question' => new QuizQuestion(['type' => $type, 'points' => 1]),
            'options' => collect(),
        ]);
    }

    public function store(Request $request, Quiz $quiz)
    {
        return $this->save($request, $quiz, new QuizQuestion(['quiz_id' => $quiz->id]));
    }

    public function edit(QuizQuestion $question)
    {
        return view('admin.questions.form', [
            'quiz' => $question->quiz,
            'question' => $question,
            'options' => $question->options,
        ]);
    }

    public function update(Request $request, QuizQuestion $question)
    {
        return $this->save($request, $question->quiz, $question);
    }

    public function destroy(QuizQuestion $question)
    {
        $quiz = $question->quiz;
        Media::delete($question->image);
        $question->delete();

        return redirect()->route('admin.quizzes.edit', $quiz)->with('status', 'Question deleted.');
    }

    private function save(Request $request, Quiz $quiz, QuizQuestion $question)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(QuizQuestion::TYPES))],
            'prompt' => ['required', 'string', 'max:5000'],
            'explanation' => ['nullable', 'string', 'max:5000'],
            'points' => ['required', 'integer', 'min:1', 'max:100'],
            'image' => $this->imageRule(),
            'options' => ['array'],
            'options.*.text' => ['nullable', 'string', 'max:1000'],
            'options.*.match' => ['nullable', 'string', 'max:1000'],
            'options.*.slot' => ['nullable', 'integer', 'min:1', 'max:50'],
            'correct' => ['nullable'],
            'tf_answer' => ['nullable', Rule::in(['true', 'false'])],
        ], $this->imageMessages(['image' => 'Picture']));

        $options = $this->optionsFrom($request, $data, $question);

        DB::transaction(function () use ($request, $quiz, $question, $data, $options) {
            $question->fill([
                'type' => $data['type'],
                'prompt' => $data['prompt'],
                'explanation' => $data['explanation'] ?? null,
                'points' => $data['points'],
            ]);
            if (! $question->exists) {
                $question->position = (int) $quiz->questions()->max('position') + 1;
            }
            $this->syncImage($request, $question, 'image', 'quiz');
            $question->save();

            // Update the answers in place: students' past answers point at their ids, so
            // deleting and re-adding them would leave every earlier attempt "not answered".
            $existing = $question->options()->get()->keyBy('id');
            $kept = [];
            foreach ($options as $i => [$key, $text, $correct, $match, $slot]) {
                $option = $existing->get((int) $key) ?? $question->options()->make();
                $option->fill(['text' => $text, 'match_text' => $match, 'is_correct' => $correct, 'slot' => $slot, 'position' => $i + 1])->save();
                $kept[] = $option->id;
            }
            $question->options()->whereKeyNot($kept)->delete();
        });

        return $request->boolean('add_another')
            ? redirect()->route('admin.quizzes.questions.create', [$quiz, 'type' => $data['type']])->with('status', 'Question saved. Add the next one.')
            : redirect()->route('admin.quizzes.edit', $quiz)->with('status', 'Question saved.');
    }

    /**
     * The answers from the form as [key, text, correct]. A key that is the id of one of the question's
     * saved answers updates that answer; any other key adds a new one.
     *
     * @return list<array{0: int|string, 1: string, 2: bool, 3: ?string, 4: ?int}>
     */
    private function optionsFrom(Request $request, array $data, QuizQuestion $question): array
    {
        if ($data['type'] === 'true_false') {
            $answer = $data['tf_answer'] ?? null;
            if (! $answer) {
                throw ValidationException::withMessages(['tf_answer' => 'Choose whether the statement is true or false.']);
            }
            // Keep the saved "true" / "false" answers (and their wording, e.g. ពិត / មិនពិត) when there are two.
            $saved = $question->exists && $question->type === 'true_false' ? $question->options()->orderBy('position')->get() : collect();
            [$yes, $no] = $saved->count() === 2 ? $saved->all() : [null, null];

            return [
                [$yes?->id ?? 'true', $yes?->text ?? __('account.true'), $answer === 'true', null, null],
                [$no?->id ?? 'false', $no?->text ?? __('account.false'), $answer === 'false', null, null],
            ];
        }

        if (array_key_exists($data['type'], QuizQuestion::WRITTEN_TYPES)) {
            return []; // no answer key: a teacher marks each answer in Exam results
        }
        if (! in_array($data['type'], QuizQuestion::CHOICE_TYPES, true)) {
            return $this->otherOptionsFrom($data);
        }

        $correct = array_map('strval', (array) ($data['correct'] ?? []));
        $options = [];
        foreach ($data['options'] ?? [] as $key => $option) {
            $text = trim((string) ($option['text'] ?? ''));
            if ($text !== '') {
                $options[] = [$key, $text, in_array((string) $key, $correct, true), null, null];
            }
        }

        $correctCount = count(array_filter($options, fn ($o) => $o[2]));
        $error = match (true) {
            count($options) < 2 => 'Add at least two answers.',
            $correctCount === 0 => 'Mark the correct answer.',
            $data['type'] === 'single' && $correctCount > 1 => 'This question type has only one correct answer.',
            default => null,
        };
        if ($error) {
            throw ValidationException::withMessages(['options' => $error]);
        }

        return $options;
    }
    /** Matching, ordering and drag & drop: their rows, checked for what each type needs. */
    private function otherOptionsFrom(array $data): array
    {
        $options = [];
        $halfPair = false;
        foreach ($data['options'] ?? [] as $key => $option) {
            $text = trim((string) ($option['text'] ?? ''));
            $match = trim((string) ($option['match'] ?? ''));
            $slot = isset($option['slot']) && $option['slot'] !== '' ? (int) $option['slot'] : null;
            if ($data['type'] === 'matching' && ($text === '') !== ($match === '')) {
                $halfPair = true;
            }
            if ($text === '' || ($data['type'] === 'matching' && $match === '')) {
                continue;
            }
            $options[] = [
                $key, $text,
                $data['type'] !== 'dragdrop' || $slot !== null, // a drag & drop word with no blank is an extra
                $data['type'] === 'matching' ? $match : null,
                $data['type'] === 'dragdrop' ? $slot : null,
            ];
        }

        $error = match ($data['type']) {
            'matching' => $halfPair ? 'Fill in both sides of every pair.' : (count($options) < 2 ? 'Add at least two pairs.' : null),
            'ordering' => count($options) < 2 ? 'Add at least two items.' : null,
            'dragdrop' => $this->dragDropError($data['prompt'], $options),
        };
        if ($error) {
            throw ValidationException::withMessages(['options' => $error]);
        }

        return $options;
    }

    private function dragDropError(string $prompt, array $options): ?string
    {
        $blanks = preg_match_all(QuizQuestion::BLANK, $prompt);
        if ($blanks === 0) {
            return 'Write ___ (three underscores) in the question where each blank goes.';
        }
        $slots = array_count_values(array_filter(array_column($options, 4)));
        for ($n = 1; $n <= $blanks; $n++) {
            if (($slots[$n] ?? 0) !== 1) {
                return "Give blank $n exactly one word.";
            }
        }
        $beyond = array_filter(array_keys($slots), fn ($n) => $n > $blanks);

        return $beyond ? 'The question has '.$blanks.' '.($blanks === 1 ? 'blank' : 'blanks').', but a word is set for blank '.min($beyond).'.' : null;
    }
}
