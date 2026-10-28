<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Support\CourseProgress;
use Illuminate\Http\Request;

/**
 * Quizzes for students: practice (instant feedback), module quizzes, timed
 * mock exams and the final exam. Grading always happens on the server.
 */
class QuizController extends Controller
{
    /** Intro page: rules, past attempts, start button. */
    public function show(Request $request, Course $course, Quiz $quiz)
    {
        $this->authorizeQuiz($request, $course, $quiz);
        $user = $request->user();

        return view('learn.quiz', [
            'course' => $course,
            'quiz' => $quiz->loadCount('questions'),
            'outline' => $course->curriculum(),
            'progress' => $user->canAccessCourse($course) ? CourseProgress::for($user, $course) : null,
            'attempts' => $quiz->attempts()->where('user_id', $user->id)->whereNotNull('submitted_at')->latest('submitted_at')->get(),
            'open' => $quiz->attempts()->where('user_id', $user->id)->whereNull('submitted_at')->latest()->first(),
            'attemptsLeft' => $quiz->attemptsLeft($user),
        ]);
    }

    public function start(Request $request, Course $course, Quiz $quiz)
    {
        $this->authorizeQuiz($request, $course, $quiz);
        $user = $request->user();

        $open = $quiz->attempts()->where('user_id', $user->id)->whereNull('submitted_at')->latest()->first();
        if ($open && ! $open->isOverdue()) {
            return redirect()->route('learn.attempt', [$course->slug, $quiz->slug, $open]);
        }
        if ($open) {
            $open->grade([]); // time ran out without a submission
        }

        if ($quiz->attemptsLeft($user) === 0) {
            return back()->with('status', __('account.no_attempts_left'));
        }
        abort_if($quiz->questions()->doesntExist(), 404);

        $ids = $quiz->questions()->pluck('id')->all();
        if ($quiz->shuffle_questions) {
            shuffle($ids);
        }

        $attempt = $quiz->attempts()->create([
            'user_id' => $user->id,
            'question_order' => $ids,
            'started_at' => now(),
        ]);

        return redirect()->route('learn.attempt', [$course->slug, $quiz->slug, $attempt]);
    }

    public function attempt(Request $request, Course $course, Quiz $quiz, QuizAttempt $attempt)
    {
        $this->authorizeAttempt($request, $course, $quiz, $attempt);

        if ($attempt->isSubmitted()) {
            return redirect()->route('learn.result', [$course->slug, $quiz->slug, $attempt]);
        }
        if ($attempt->isOverdue()) {
            $attempt->grade([]);

            return redirect()->route('learn.result', [$course->slug, $quiz->slug, $attempt]);
        }

        return view('learn.attempt', [
            'course' => $course,
            'quiz' => $quiz,
            'attempt' => $attempt,
            'questions' => $attempt->orderedQuestions(),
            'secondsLeft' => $attempt->deadline() ? max(0, now()->diffInSeconds($attempt->deadline(), false)) : null,
        ]);
    }

    public function submit(Request $request, Course $course, Quiz $quiz, QuizAttempt $attempt)
    {
        $this->authorizeAttempt($request, $course, $quiz, $attempt);

        if (! $attempt->isSubmitted()) {
            // Past the deadline (plus grace) nothing sent now is counted.
            $attempt->grade($attempt->isOverdue() ? [] : (array) $request->input('answers', []));
            CourseProgress::for($request->user(), $course)->recordCompletion();
        }

        return redirect()->route('learn.result', [$course->slug, $quiz->slug, $attempt]);
    }

    public function result(Request $request, Course $course, Quiz $quiz, QuizAttempt $attempt)
    {
        $this->authorizeAttempt($request, $course, $quiz, $attempt);
        abort_unless($attempt->isSubmitted(), 404);

        $progress = CourseProgress::for($request->user(), $course);

        return view('learn.result', [
            'course' => $course,
            'quiz' => $quiz,
            'attempt' => $attempt,
            'questions' => $attempt->orderedQuestions(),
            'progress' => $progress,
            'attemptsLeft' => $quiz->attemptsLeft($request->user()),
            'next' => $attempt->passed || $quiz->isPractice() ? $progress->itemAfter($quiz) : null,
            'certificateCode' => $request->user()->enrollments()->where('course_id', $course->id)->value('certificate_code'),
        ]);
    }

    /** Practice mode: check one answer straight away. */
    public function check(Request $request, Course $course, Quiz $quiz, QuizQuestion $question)
    {
        $this->authorizeQuiz($request, $course, $quiz);
        abort_unless($quiz->isPractice() && $question->quiz_id === $quiz->id, 404);

        $chosen = array_map('intval', (array) $request->input('chosen', []));

        return response()->json([
            'correct' => $question->load('options')->isAnsweredCorrectly($chosen),
            'correct_ids' => $question->correctOptionIds(),
            'explanation' => $question->explanation,
        ]);
    }

    private function authorizeQuiz(Request $request, Course $course, Quiz $quiz): void
    {
        abort_unless($quiz->is_published || $request->user()->isAdmin(), 404);
        if (! $request->user()->canAccessCourse($course) && ! ($quiz->is_preview && $course->is_published)) {
            abort(redirect()->route('courses.show', $course->slug)->with('checkout_notice', __('account.need_access')));
        }
    }

    private function authorizeAttempt(Request $request, Course $course, Quiz $quiz, QuizAttempt $attempt): void
    {
        $this->authorizeQuiz($request, $course, $quiz);
        abort_unless($attempt->user_id === $request->user()->id, 404);
    }
}
