<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Quiz;
use App\Support\Slug;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Practice tests, module quizzes, mock exams and final exams of a course. */
class QuizController extends Controller
{
    public function create(Request $request, Course $course)
    {
        $type = array_key_exists((string) $request->query('type'), Quiz::TYPES) ? $request->query('type') : 'quiz';

        return view('admin.quizzes.form', [
            'course' => $course,
            'quiz' => new Quiz(array_merge([
                'type' => $type,
                'course_module_id' => $request->integer('module') ?: null,
                'pass_percent' => 70,
                'show_answers' => true,
                'is_published' => true,
            ], match ($type) {
                'mock' => ['time_limit' => 30, 'shuffle_questions' => true],
                'final' => ['time_limit' => 45, 'shuffle_questions' => true, 'max_attempts' => 3, 'pass_percent' => 75],
                default => [],
            })),
        ]);
    }

    public function store(Request $request, Course $course)
    {
        return $this->save($request, $course, new Quiz(['course_id' => $course->id]));
    }

    public function edit(Quiz $quiz)
    {
        // Students only: admins trying the test with "View as student" are not counted.
        $done = fn () => $quiz->attempts()->whereNotNull('submitted_at')->whereHas('user', fn ($q) => $q->where('role', 'student'));
        $total = $done()->count();

        return view('admin.quizzes.form', [
            'course' => $quiz->course,
            'quiz' => $quiz->load('questions.options'),
            'stats' => [
                'attempts' => $total,
                'students' => $done()->distinct('user_id')->count('user_id'),
                'pass_rate' => $total ? (int) round($done()->where('passed', true)->count() * 100 / $total) : null,
                'average' => (int) round((float) $done()->avg('percent')),
            ],
        ]);
    }

    public function update(Request $request, Quiz $quiz)
    {
        return $this->save($request, $quiz->course, $quiz);
    }

    public function destroy(Quiz $quiz)
    {
        $course = $quiz->course;
        $quiz->delete();

        return redirect()->route('admin.courses.curriculum', $course)->with('status', 'Quiz deleted.');
    }

    private function save(Request $request, Course $course, Quiz $quiz)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Quiz::TYPES))],
            'title' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190'],
            'course_module_id' => ['nullable', Rule::exists('course_modules', 'id')->where('course_id', $course->id)],
            'description' => ['nullable', 'string', 'max:3000'],
            'time_limit' => ['nullable', 'integer', 'min:1', 'max:600'],
            'pass_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $moduleChanged = $quiz->exists && $quiz->course_module_id != ($data['course_module_id'] ?? null);
        $quiz->fill([
            'type' => $data['type'],
            'title' => $data['title'],
            'course_module_id' => $data['course_module_id'] ?? null,
            'description' => $data['description'] ?? null,
            // Practice tests are untimed and unlimited.
            'time_limit' => $data['type'] === 'practice' ? null : ($data['time_limit'] ?? null),
            'max_attempts' => $data['type'] === 'practice' ? null : ($data['max_attempts'] ?? null),
            'pass_percent' => $data['pass_percent'],
            'shuffle_questions' => $request->boolean('shuffle_questions'),
            'show_answers' => $request->boolean('show_answers'),
            'is_preview' => $request->boolean('is_preview'),
            'is_published' => $request->boolean('is_published'),
        ]);
        $quiz->slug = Slug::unique(($data['slug'] ?? null) ?: $data['title'], Quiz::where('course_id', $course->id), $quiz->id);
        if (! $quiz->exists || $moduleChanged) {
            $quiz->position = max(
                (int) $course->lessons()->where('course_module_id', $quiz->course_module_id)->max('position'),
                (int) $course->quizzes()->where('course_module_id', $quiz->course_module_id)->max('position'),
            ) + 1;
        }
        $quiz->save();

        return redirect()->route('admin.quizzes.edit', $quiz)->with('status', 'Quiz saved.');
    }
}
