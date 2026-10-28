<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * How far a student is through a course. Complete = every published lesson
 * marked done and every required quiz (module quizzes, final exam) passed.
 */
class CourseProgress
{
    public array $completedLessonIds;

    public array $passedQuizIds;

    public int $lessonsTotal;

    public int $requiredTotal;

    public int $done;

    public int $total;

    public int $percent;

    public bool $complete;

    private ?Collection $items = null;

    public function __construct(public User $user, public Course $course)
    {
        $lessonIds = $course->lessons()->where('is_published', true)->pluck('id');
        $required = $course->quizzes()->where('is_published', true)->whereIn('type', Quiz::REQUIRED_TYPES)->pluck('id');

        $this->completedLessonIds = DB::table('lesson_progress')->where('user_id', $user->id)
            ->whereIn('course_lesson_id', $lessonIds)->pluck('course_lesson_id')->map(fn ($id) => (int) $id)->all();
        $this->passedQuizIds = QuizAttempt::where('user_id', $user->id)->where('passed', true)
            ->whereIn('quiz_id', $course->quizzes()->pluck('id'))->distinct()->pluck('quiz_id')->map(fn ($id) => (int) $id)->all();

        $this->lessonsTotal = $lessonIds->count();
        $this->requiredTotal = $required->count();
        $this->total = $this->lessonsTotal + $this->requiredTotal;
        $this->done = count($this->completedLessonIds) + count(array_intersect($this->passedQuizIds, $required->map(fn ($id) => (int) $id)->all()));
        $this->percent = $this->total ? (int) floor($this->done * 100 / $this->total) : 0;
        $this->complete = $this->total > 0 && $this->done >= $this->total;
    }

    public static function for(User $user, Course $course): self
    {
        return new self($user, $course);
    }

    public function lessonDone(int $lessonId): bool
    {
        return in_array($lessonId, $this->completedLessonIds, true);
    }

    public function quizPassed(int $quizId): bool
    {
        return in_array($quizId, $this->passedQuizIds, true);
    }

    public function itemDone(CourseLesson|Quiz $item): bool
    {
        return $item instanceof CourseLesson ? $this->lessonDone($item->id) : $this->quizPassed($item->id);
    }

    /** Published lessons and tests in course order. */
    public function items(): Collection
    {
        return $this->items ??= $this->course->curriculum()->flatMap(fn ($section) => $section['items'])->values();
    }

    /** Where "Continue" goes: the first lesson not done or required test not passed (else the first item). */
    public function nextItem(): CourseLesson|Quiz|null
    {
        return $this->items()->first(fn ($item) => $item instanceof CourseLesson
            ? ! $this->lessonDone($item->id)
            : ($item->isRequired() && ! $this->quizPassed($item->id))
        ) ?? $this->items()->first();
    }

    /** The lesson or test that comes after $current in the course. */
    public function itemAfter(CourseLesson|Quiz $current): CourseLesson|Quiz|null
    {
        $index = $this->items()->search(fn ($item) => $item::class === $current::class && $item->id === $current->id);

        return $index === false ? null : $this->items()->get($index + 1);
    }

    /** The last time the student finished a lesson or a test in this course. */
    public function lastActivityAt(): ?Carbon
    {
        $lesson = DB::table('lesson_progress')->where('user_id', $this->user->id)
            ->whereIn('course_lesson_id', $this->course->lessons()->reorder()->select('id'))->max('completed_at');
        $quiz = QuizAttempt::where('user_id', $this->user->id)
            ->whereIn('quiz_id', $this->course->quizzes()->reorder()->select('id'))->max('submitted_at');
        $latest = max((string) $lesson, (string) $quiz);

        return $latest === '' ? null : Carbon::parse($latest);
    }

    public static function urlFor(Course $course, CourseLesson|Quiz $item): string
    {
        return $item instanceof Quiz
            ? route('learn.quiz', [$course->slug, $item->slug])
            : route('learn.lesson', [$course->slug, $item->slug]);
    }

    /** Stamp the enrollment as completed (and give it a certificate code) the first time. */
    public function recordCompletion(): ?Enrollment
    {
        if (! $this->complete) {
            return null;
        }

        $enrollment = Enrollment::where('user_id', $this->user->id)->where('course_id', $this->course->id)->first();
        if ($enrollment && ! $enrollment->completed_at) {
            $enrollment->forceFill([
                'completed_at' => now(),
                'certificate_code' => $this->course->certificate_enabled ? self::newCertificateCode() : null,
            ])->save();
        }

        return $enrollment;
    }

    public static function newCertificateCode(): string
    {
        do {
            $code = 'TIC-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
        } while (Enrollment::where('certificate_code', $code)->exists());

        return $code;
    }
}
