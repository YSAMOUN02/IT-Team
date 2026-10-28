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
            'correct' => ['nullable'],
            'tf_answer' => ['nullable', Rule::in(['true', 'false'])],
        ]);

        $options = $this->optionsFrom($request, $data);

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

            $question->options()->delete();
            foreach ($options as $i => [$text, $correct]) {
                $question->options()->create(['text' => $text, 'is_correct' => $correct, 'position' => $i + 1]);
            }
        });

        return $request->boolean('add_another')
            ? redirect()->route('admin.quizzes.questions.create', [$quiz, 'type' => $data['type']])->with('status', 'Question saved. Add the next one.')
            : redirect()->route('admin.quizzes.edit', $quiz)->with('status', 'Question saved.');
    }

    /** @return list<array{0: string, 1: bool}> */
    private function optionsFrom(Request $request, array $data): array
    {
        if ($data['type'] === 'true_false') {
            $answer = $data['tf_answer'] ?? null;
            if (! $answer) {
                throw ValidationException::withMessages(['tf_answer' => 'Choose whether the statement is true or false.']);
            }

            return [[__('account.true'), $answer === 'true'], [__('account.false'), $answer === 'false']];
        }

        $correct = array_map('strval', (array) ($data['correct'] ?? []));
        $options = [];
        foreach ($data['options'] ?? [] as $key => $option) {
            $text = trim((string) ($option['text'] ?? ''));
            if ($text !== '') {
                $options[] = [$text, in_array((string) $key, $correct, true)];
            }
        }

        $correctCount = count(array_filter($options, fn ($o) => $o[1]));
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
}
