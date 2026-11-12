<?php

namespace App\Support;

use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The progress report for Excel: one class or one student. Four sheets:
 *  Summary - one row per student and course: access, lessons done, tests passed, progress %, status
 *  Lessons - every published lesson of each course: done or not, and when
 *  Tests   - every published test: tries, best score, result
 *  Tries   - every submitted try, with its date, score and result
 * Progress counts the same way as CourseProgress (published lessons + required tests passed).
 */
class ProgressReport
{
    /** @var array<int, array{lessons: Collection, tests: Collection}> course id => its outline */
    private array $outlines = [];

    /**
     * @param  Collection<int, User>  $students
     * @param  callable(User): Collection<int, Course>  $coursesFor  the courses to report for each student
     */
    public function __construct(private Collection $students, private $coursesFor) {}

    public static function forClass(Classroom $classroom, ?Course $only = null): self
    {
        $courses = $only ? collect([$only]) : $classroom->courses()->get();

        return new self($classroom->students()->get(), fn () => $courses);
    }

    /** One student: every course they have (or had) access to. */
    public static function forStudent(User $student): self
    {
        $courses = Course::whereIn('id', Enrollment::where('user_id', $student->id)->pluck('course_id'))->orderBy('title')->get();

        return new self(collect([$student]), fn () => $courses);
    }

    public function workbook(): XlsxWriter
    {
        $ids = $this->students->pluck('id');
        $courses = $this->students->flatMap(fn ($s) => ($this->coursesFor)($s))->unique('id')->values();
        foreach ($courses as $course) {
            $this->outline($course);
        }
        $lessonIds = collect($this->outlines)->flatMap(fn ($o) => $o['lessons']->pluck('id'));
        $quizIds = collect($this->outlines)->flatMap(fn ($o) => $o['tests']->pluck('id'));

        // Everything in a few queries, then grouped in memory.
        $done = DB::table('lesson_progress')->whereIn('user_id', $ids)->whereIn('course_lesson_id', $lessonIds)->get()
            ->groupBy('user_id')->map(fn ($rows) => $rows->mapWithKeys(fn ($r) => [(int) $r->course_lesson_id => $r->completed_at]));
        $tries = QuizAttempt::whereIn('user_id', $ids)->whereIn('quiz_id', $quizIds)->whereNotNull('submitted_at')
            ->orderBy('submitted_at')->orderBy('id')->get()->groupBy(fn ($a) => $a->user_id.'-'.$a->quiz_id);
        $enrollments = Enrollment::whereIn('user_id', $ids)->whereIn('course_id', $courses->pluck('id'))->get()->keyBy(fn ($e) => $e->user_id.'-'.$e->course_id);
        $classes = Classroom::forStudents($ids);

        $summary = $lessonRows = $testRows = $tryRows = [];
        foreach ($this->students as $student) {
            $who = [$student->name, $student->email, $student->phone, ($classes[$student->id] ?? collect())->pluck('name')->implode(', ')];
            $doneLessons = $done[$student->id] ?? collect();

            foreach (($this->coursesFor)($student) as $course) {
                ['lessons' => $lessons, 'tests' => $tests] = $this->outlines[$course->id];
                $enrollment = $enrollments[$student->id.'-'.$course->id] ?? null;
                $last = null;

                foreach ($lessons as $i => $lesson) {
                    $at = $doneLessons[$lesson->id] ?? null;
                    $last = max($last, $at);
                    $lessonRows[] = [$student->name, $course->title, $lesson->module?->title ?? '', $i + 1, $lesson->title, $at ? 'Yes' : 'No', $this->date($at)];
                }

                $passedRequired = 0;
                $waiting = 0;
                foreach ($tests as $test) {
                    $list = $tries[$student->id.'-'.$test->id] ?? collect();
                    $best = $list->sortByDesc('percent')->first();
                    $passed = $list->contains('passed', true);
                    $isWaiting = ! $passed && $list->contains('needs_marking', true);
                    $passedRequired += $passed && $test->isRequired() ? 1 : 0;
                    $waiting += $isWaiting ? 1 : 0;
                    $lastTry = $list->last()?->submitted_at;
                    $last = max($last, $lastTry?->toDateTimeString());
                    $testRows[] = [
                        $student->name, $course->title, $test->module?->title ?? '', $test->title, Quiz::TYPES[$test->type] ?? $test->type,
                        $test->isRequired() ? 'Yes' : 'No', $list->count(),
                        $best ? $best->percent : '', $best ? $best->score.' / '.$best->max_score : '',
                        $list->isEmpty() ? 'Not taken' : ($passed ? 'Passed' : ($isWaiting ? 'Waiting for marking' : 'Not passed')),
                        $this->date($lastTry),
                    ];
                    foreach ($list->values() as $n => $try) {
                        $tryRows[] = [
                            $student->name, $course->title, $test->title, $n + 1, $this->date($try->started_at), $this->date($try->submitted_at),
                            $try->started_at && $try->submitted_at->gt($try->started_at) ? round($try->started_at->diffInSeconds($try->submitted_at) / 60, 1) : '',
                            $try->score, $try->max_score, $try->percent,
                            $try->passed ? 'Passed' : ($try->needs_marking ? 'Waiting for marking' : 'Not passed'),
                        ];
                    }
                }

                $lessonsDone = $lessons->filter(fn ($l) => isset($doneLessons[$l->id]))->count();
                $required = $tests->filter->isRequired()->count();
                $total = $lessons->count() + $required;
                $percent = $total ? (int) floor(($lessonsDone + $passedRequired) * 100 / $total) : 0;
                $status = match (true) {
                    ! $enrollment => 'No access',
                    $enrollment->completed_at !== null, // finished once (lessons added later do not undo it), as the class page shows
                    $total > 0 && $lessonsDone + $passedRequired >= $total => 'Completed',
                    $lessonsDone + $passedRequired > 0 || $last !== null => 'In progress',
                    default => 'Not started',
                };
                $summary[] = array_merge($who, [
                    $course->title,
                    $enrollment ? $this->date($enrollment->starts_at, false) : '',
                    $enrollment ? ($enrollment->expires_at ? $this->date($enrollment->expires_at, false) : 'Lifetime') : '',
                    $percent, $lessonsDone.' / '.$lessons->count(), $passedRequired.' / '.$required, $waiting ?: '',
                    $status, $this->date($last), $this->date($enrollment?->completed_at, false), $enrollment?->certificate_code ?? '',
                ]);
            }
        }

        return (new XlsxWriter)
            ->sheet('Summary', ['Student', 'Email', 'Phone', 'Class', 'Course', 'Access from', 'Access until', 'Progress %', 'Lessons done', 'Required tests passed', 'Tests waiting for marking', 'Status', 'Last active', 'Completed on', 'Certificate'], $summary)
            ->sheet('Lessons', ['Student', 'Course', 'Module', '#', 'Lesson', 'Done', 'Done on'], $lessonRows)
            ->sheet('Tests', ['Student', 'Course', 'Module', 'Test', 'Type', 'Required', 'Tries', 'Best %', 'Best score', 'Result', 'Last try'], $testRows)
            ->sheet('Tries', ['Student', 'Course', 'Test', 'Try', 'Started', 'Submitted', 'Minutes', 'Score', 'Out of', '%', 'Result'], $tryRows);
    }

    /** A course's published lessons and tests, in course order, with their module. */
    private function outline(Course $course): array
    {
        return $this->outlines[$course->id] ??= (function () use ($course) {
            $items = $course->curriculum()->flatMap(fn ($section) => $section['items']->each(fn ($item) => $item->setRelation('module', $section['module'])));

            return [
                'lessons' => $items->filter(fn ($item) => $item instanceof CourseLesson)->values(),
                'tests' => $items->filter(fn ($item) => $item instanceof Quiz)->values(),
            ];
        })();
    }

    private function date(mixed $value, bool $time = true): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return Carbon::parse($value)->format($time ? 'Y-m-d H:i' : 'Y-m-d');
    }
}
