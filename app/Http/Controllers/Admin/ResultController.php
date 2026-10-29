<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\RetakeRequest;
use App\Support\CourseProgress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Admin > Exam results: every test students took, and each attempt question by question. */
class ResultController extends Controller
{
    public const RESULTS = ['passed' => 'Passed', 'failed' => 'Not passed', 'unfinished' => 'Not submitted'];

    /** Where each student stands in a course (Admin > Courses > Results). */
    public const COURSE_STATUSES = [
        'learning' => 'Learning',
        'completed' => 'Completed',
        'retake' => 'Needs to retake',
        'not_started' => 'Not started',
        'ended' => 'Access ended',
    ];

    public function index(Request $request)
    {
        $filters = $request->only('q', 'course', 'type', 'result', 'quiz');
        $attempts = $this->filtered($filters)
            ->with(['user:id,name,email', 'quiz:id,course_id,title,type,pass_percent,time_limit', 'quiz.course:id,title'])
            ->latest('started_at')->latest('id')
            ->paginate(30)->withQueryString();

        // "Try 2 of 3": this attempt's place among the student's attempts at the same test, by attempt id.
        $tries = [];
        $related = QuizAttempt::whereIn('user_id', $attempts->pluck('user_id')->unique())
            ->whereIn('quiz_id', $attempts->pluck('quiz_id')->unique())
            ->orderBy('started_at')->orderBy('id')->get(['id', 'user_id', 'quiz_id'])
            ->groupBy(fn ($a) => $a->user_id.'-'.$a->quiz_id);
        foreach ($related as $group) {
            foreach ($group->values() as $i => $a) {
                $tries[$a->id] = [$i + 1, $group->count()];
            }
        }

        $submitted = fn () => $this->filtered($filters)->whereNotNull('submitted_at');
        $count = $submitted()->count();

        return view('admin.results.index', [
            'attempts' => $attempts,
            'tries' => $tries,
            'stats' => [
                'attempts' => $count,
                'students' => $submitted()->distinct('user_id')->count('user_id'),
                'average' => (int) round((float) $submitted()->avg('percent')),
                'pass_rate' => $count ? (int) round($submitted()->where('passed', true)->count() * 100 / $count) : null,
                'unfinished' => $this->filtered($filters)->whereNull('submitted_at')->count(),
            ],
            'courses' => Course::orderBy('title')->get(['id', 'title']),
            'quiz' => isset($filters['quiz']) ? Quiz::with('course:id,title')->find($filters['quiz']) : null,
        ]);
    }

    public function show(QuizAttempt $attempt)
    {
        $attempt->load(['user', 'quiz.course']);
        $questions = $attempt->isSubmitted() ? $attempt->orderedQuestions() : collect();

        return view('admin.results.show', [
            'attempt' => $attempt,
            'quiz' => $attempt->quiz,
            'rows' => $questions->map(function ($question) use ($attempt) {
                $chosen = array_map('intval', $attempt->answers[$question->id] ?? []);

                return ['question' => $question, 'chosen' => $chosen, 'right' => $question->isAnsweredCorrectly($chosen)];
            }),
            'others' => QuizAttempt::where('user_id', $attempt->user_id)->where('quiz_id', $attempt->quiz_id)
                ->orderBy('started_at')->orderBy('id')->get(),
            'attemptsLeft' => $attempt->quiz->attemptsLeft($attempt->user),
            'retakes' => RetakeRequest::where('user_id', $attempt->user_id)->where('quiz_id', $attempt->quiz_id)->latest()->latest('id')->get(),
        ]);
    }

    /** Admin > Courses > a course > Results: the whole class on one page, one column per test. */
    public function course(Request $request, Course $course)
    {
        $book = $this->gradebook($course);
        $show = array_key_exists((string) $request->query('show'), self::COURSE_STATUSES) ? $request->query('show') : null;
        $final = $book['tests']->firstWhere('type', 'final');
        $finalTakers = $final ? $book['rows']->filter(fn ($row) => $row['scores'][$final->id]) : collect();

        return view('admin.courses.results', $book + [
            'course' => $course,
            'show' => $show,
            'shown' => $show ? $book['rows']->where('status', $show)->values() : $book['rows'],
            'counts' => $book['rows']->countBy('status'),
            'final' => $final,
            'finalPassed' => $finalTakers->filter(fn ($row) => $row['scores'][$final->id]['passed'])->count(),
            'finalTakers' => $finalTakers->count(),
        ]);
    }

    /** The same table as a CSV file for Excel (with a BOM, so Khmer names show correctly). */
    public function courseExport(Request $request, Course $course)
    {
        ['tests' => $tests, 'rows' => $rows] = $this->gradebook($course);
        if (array_key_exists((string) $request->query('show'), self::COURSE_STATUSES)) {
            $rows = $rows->where('status', $request->query('show'));
        }

        return response()->streamDownload(function () use ($tests, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_merge(['Student', 'Email', 'Phone', 'Progress %', 'Lessons and tests done'],
                $tests->map(fn ($test) => $test->title.' (best %)')->all(), ['Result', 'Needs to retake', 'Certificate', 'Access']));
            foreach ($rows as $row) {
                $enrollment = $row['enrollment'];
                fputcsv($out, array_merge(
                    [$enrollment->user->name, $enrollment->user->email, $enrollment->user->phone, $row['progress']->percent, $row['progress']->done.'/'.$row['progress']->total],
                    $tests->map(fn ($test) => $row['scores'][$test->id]['best']->percent ?? '')->all(),
                    [self::COURSE_STATUSES[$row['status']], $row['retake']->pluck('title')->implode(', '), $enrollment->certificate_code,
                        $enrollment->disabled_at ? 'Turned off' : (! $enrollment->inPeriod() ? 'Ended' : ($enrollment->expires_at ? 'Until '.$enrollment->expires_at->format('Y-m-d') : 'Lifetime'))],
                ));
            }
            fclose($out);
        }, str($course->slug)->slug().'-results-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Every student of the course with their progress and, for each test (practice left out),
     * their best submitted score, whether any try passed, and how many tries.
     */
    private function gradebook(Course $course): array
    {
        // Column names as the curriculum shows them: "Module 2" for that module's quiz, "Mock", "Final".
        $tests = collect();
        $labels = [];
        foreach ($course->curriculum() as $i => $section) {
            foreach ($section['items']->filter(fn ($item) => $item instanceof Quiz && ! $item->isPractice()) as $test) {
                $name = match (true) {
                    $test->type === 'quiz' && $section['module'] !== null => 'Module '.($i + 1),
                    default => ['quiz' => 'Quiz', 'mock' => 'Mock', 'final' => 'Final'][$test->type] ?? ucfirst($test->type),
                };
                $taken = count(array_filter($labels, fn ($label) => $label === $name || str_starts_with($label, "$name (")));
                $labels[$test->id] = $taken ? "$name (".($taken + 1).')' : $name;
                $tests->push($test);
            }
        }
        $enrollments = $course->enrollments()->with('user')->whereHas('user', fn ($q) => $q->where('role', 'student'))->get();
        $attempts = QuizAttempt::whereIn('quiz_id', $tests->pluck('id'))->whereIn('user_id', $enrollments->pluck('user_id'))
            ->whereNotNull('submitted_at')->orderByDesc('percent')->orderBy('id')
            ->get(['id', 'user_id', 'quiz_id', 'percent', 'passed'])
            ->groupBy(['user_id', 'quiz_id']);
        $asked = RetakeRequest::pending()->whereIn('quiz_id', $tests->pluck('id'))->whereIn('user_id', $enrollments->pluck('user_id'))->pluck('user_id')->flip();

        $rows = $enrollments->map(function ($enrollment) use ($course, $tests, $attempts, $asked) {
            $progress = CourseProgress::for($enrollment->user, $course);
            $scores = $tests->mapWithKeys(function ($test) use ($attempts, $enrollment) {
                $tries = $attempts[$enrollment->user_id][$test->id] ?? collect();

                return [$test->id => $tries->isEmpty() ? null : ['best' => $tries->first(), 'passed' => $tries->contains('passed', true), 'tries' => $tries->count()]];
            });
            // A module quiz or final exam taken but not passed yet.
            $retake = $tests->filter(fn ($test) => $test->isRequired() && $scores[$test->id] && ! $scores[$test->id]['passed'])->values();

            return [
                'enrollment' => $enrollment,
                'progress' => $progress,
                'scores' => $scores,
                'retake' => $retake,
                'asked' => isset($asked[$enrollment->user_id]), // waiting for a teacher to answer a re-exam request
                'status' => match (true) {
                    (bool) $enrollment->completed_at => 'completed',
                    $retake->isNotEmpty() => 'retake',
                    ! $enrollment->isActive() => 'ended',
                    $progress->done === 0 && $scores->filter()->isEmpty() => 'not_started',
                    default => 'learning',
                },
            ];
        })->sortBy([fn ($a, $b) => $b['progress']->percent <=> $a['progress']->percent, fn ($a, $b) => strcmp($a['enrollment']->user->name, $b['enrollment']->user->name)])->values();

        return ['tests' => $tests, 'labels' => $labels, 'rows' => $rows];
    }

    /** Students' attempts (admins trying a test with "View as student" are left out), narrowed by the toolbar. */
    private function filtered(array $filters): Builder
    {
        return QuizAttempt::query()
            ->whereHas('user', fn ($q) => $q->where('role', 'student')
                ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('name', 'like', "%$term%")->orWhere('email', 'like', "%$term%"))))
            ->whereHas('quiz', fn ($q) => $q
                ->when($filters['course'] ?? null, fn ($q, $id) => $q->where('course_id', $id))
                ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type)))
            ->when($filters['quiz'] ?? null, fn ($q, $id) => $q->where('quiz_id', $id))
            ->when(($filters['result'] ?? null) === 'passed', fn ($q) => $q->whereNotNull('submitted_at')->where('passed', true))
            ->when(($filters['result'] ?? null) === 'failed', fn ($q) => $q->whereNotNull('submitted_at')->where('passed', false))
            ->when(($filters['result'] ?? null) === 'unfinished', fn ($q) => $q->whereNull('submitted_at'));
    }
}
