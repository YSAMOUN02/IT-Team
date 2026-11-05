<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Admin > Classes: group the students who study one or more courses together, then follow just
 * them: an overview across the courses, and per course their progress, scores, last activity and
 * who needs help. Course access is not changed here.
 */
class ClassroomController extends Controller
{
    /** No lesson or test finished for this many days: the class page asks the teacher to check in. */
    public const QUIET_DAYS = 7;

    /** The reason shown when a student's account is turned off: they cannot open any course. */
    public const ACCOUNT_OFF = 'Account turned off';

    /** The class page's groups, as filter tabs. */
    public const VIEWS = [
        'help' => 'Needs help',
        'learning' => 'Learning',
        'completed' => 'Completed',
        'not_started' => 'Not started',
        'no_access' => 'No access',
    ];

    public function index(Request $request)
    {
        $show = array_key_exists((string) $request->query('show'), Classroom::STATUSES) ? $request->query('show') : null;
        $courseId = $request->integer('course') ?: null;
        $classes = Classroom::with('courses:id,title,slug,image')->withCount('students')
            ->when($courseId, fn ($q, $id) => $q->whereHas('courses', fn ($c) => $c->whereKey($id)))
            ->orderByRaw('starts_on is null')->orderByDesc('starts_on')->orderByDesc('id')->get();

        return view('admin.classes.index', [
            'classes' => $show ? $classes->filter(fn ($class) => $class->status() === $show)->values() : $classes,
            'counts' => $classes->countBy(fn ($class) => $class->status()),
            'total' => $classes->count(),
            'show' => $show,
            'courses' => Course::orderBy('title')->get(['id', 'title']),
            'courseId' => $courseId,
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.classes.form', [
            'classroom' => new Classroom(),
            'chosen' => array_filter([$request->integer('course') ?: null]),
            'courses' => Course::orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function store(Request $request)
    {
        [$data, $courseIds] = $this->validated($request);
        $classroom = Classroom::create($data);
        $classroom->syncCourses($courseIds);

        return redirect()->route('admin.classes.show', $classroom)->with('status', 'Class created. Now add its students.');
    }

    public function edit(Classroom $classroom)
    {
        return view('admin.classes.form', [
            'classroom' => $classroom,
            'chosen' => $classroom->courses()->pluck('courses.id')->all(),
            'courses' => Course::orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function update(Request $request, Classroom $classroom)
    {
        [$data, $courseIds] = $this->validated($request);
        $classroom->update($data);
        $classroom->syncCourses($courseIds);

        return redirect()->route('admin.classes.show', $classroom)->with('status', 'Class saved.');
    }

    public function destroy(Classroom $classroom)
    {
        $classroom->delete();

        return redirect()->route('admin.classes.index')->with('status', "Class “{$classroom->name}” deleted. Its students and their course access are unchanged.");
    }

    /**
     * The teacher's page. With several courses it opens on the overview (one column per course);
     * a course tab (or a class with one course) shows that course's scores test by test.
     */
    public function show(Request $request, Classroom $classroom)
    {
        $courses = $classroom->courses()->get();
        $course = $courses->count() === 1 ? $courses->first() : $courses->firstWhere('id', $request->integer('course'));
        $show = array_key_exists((string) $request->query('show'), self::VIEWS) ? $request->query('show') : null;
        $book = $course ? $this->courseRows($classroom, $course) : $this->overview($classroom, $courses);
        $rows = $book['rows'];
        $inClass = $classroom->students()->pluck('users.id');

        return view('admin.classes.show', $book + [
            'classroom' => $classroom,
            'courses' => $courses,
            'course' => $course,
            'shown' => $show ? $rows->filter(fn ($row) => in_array($show, $row['groups'], true))->values() : $rows,
            'show' => $show,
            'unreadChats' => ChatMessage::unreadByAdmins()->whereIn('student_id', $inClass)->pluck('student_id')->countBy(),
            'counts' => collect(array_keys(self::VIEWS))->mapWithKeys(fn ($key) => [$key => $rows->filter(fn ($row) => in_array($key, $row['groups'], true))->count()]),
            // For "Add students": everyone not in the class yet, those who have more of its courses first.
            'pickable' => User::where('role', 'student')->whereNotIn('id', $inClass)
                ->withCount(['enrollments as courses_had' => fn ($q) => $q->whereIn('course_id', $courses->pluck('id'))])
                ->orderByDesc('courses_had')->orderBy('name')->limit(1000)->get(['id', 'name', 'email', 'phone']),
        ]);
    }

    public function addStudents(Request $request, Classroom $classroom)
    {
        $data = $request->validate(['students' => ['required', 'array', 'max:500'], 'students.*' => ['integer']], [
            'students.required' => 'Tick at least one student.',
        ]);
        $ids = User::where('role', 'student')->whereIn('id', $data['students'])->pluck('id');
        $classroom->students()->syncWithoutDetaching($ids);

        $courses = $classroom->courses()->get();
        $missing = User::whereIn('id', $ids)->get()
            ->filter(fn (User $user) => $courses->contains(fn (Course $course) => ! $user->canAccessCourse($course)))->count();
        $where = $courses->count() > 1 ? 'one of the courses' : 'the course';

        return redirect()->route('admin.classes.show', $classroom)->with('status', $ids->count().' '.str('student')->plural($ids->count()).' added.'
            .($missing ? " $missing cannot open $where yet: give them access on the course’s Students page." : ''));
    }

    /** From a student's page: put this one student in a class, and stay on their page. */
    public function addToClass(Request $request, User $user)
    {
        abort_if($user->isAdmin(), 404);
        $classroom = Classroom::findOrFail($request->validate(['classroom_id' => ['required', 'integer']])['classroom_id']);
        $classroom->students()->syncWithoutDetaching([$user->id]);
        $cannot = $classroom->courses()->get()->reject(fn (Course $course) => $user->canAccessCourse($course))->pluck('title');

        return back()->with('status', "Added to the class “{$classroom->name}”."
            .($cannot->isNotEmpty() ? ' They cannot open '.$cannot->implode(', ').' yet: use Give access.' : ''));
    }

    public function removeStudent(Classroom $classroom, User $user)
    {
        $classroom->students()->detach($user->id);

        return back()->with('status', "{$user->name} left the class. Their course access is unchanged.");
    }

    /** The table on screen (overview, or one course with ?course=) as a CSV file for Excel, with a BOM for Khmer names. */
    public function export(Request $request, Classroom $classroom)
    {
        $courses = $classroom->courses()->get();
        $course = $courses->count() === 1 ? $courses->first() : $courses->firstWhere('id', $request->integer('course'));
        $name = str($classroom->name)->slug().($course && $courses->count() > 1 ? '-'.str($course->slug)->slug() : '').'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($classroom, $courses, $course) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            if ($course) {
                ['tests' => $tests, 'labels' => $labels, 'rows' => $rows] = $this->courseRows($classroom, $course);
                fputcsv($out, array_merge(['Student', 'Email', 'Phone', 'Progress %', 'Done', 'Last active'],
                    $tests->map(fn ($test) => $labels[$test->id].' (best %)')->all(), ['Status', 'Needs to retake', 'Certificate']));
                foreach ($rows as $row) {
                    fputcsv($out, array_merge(
                        [$row['student']->name, $row['student']->email, $row['student']->phone,
                            $row['enrollment'] ? $row['progress']->percent : '', $row['enrollment'] ? $row['progress']->done.'/'.$row['progress']->total : '',
                            $row['last_active']?->format('Y-m-d H:i') ?? ''],
                        $tests->map(fn ($test) => $row['scores'][$test->id]['best']->percent ?? '')->all(),
                        [$row['label'], $row['retake']->map(fn ($test) => $labels[$test->id])->implode(', '), $row['enrollment']?->certificate_code],
                    ));
                }
            } else {
                $rows = $this->overview($classroom, $courses)['rows'];
                fputcsv($out, array_merge(['Student', 'Email', 'Phone'],
                    $courses->flatMap(fn ($c) => [$c->title.' (progress %)', $c->title.' (status)'])->all(), ['Last active', 'Needs help']));
                foreach ($rows as $row) {
                    fputcsv($out, array_merge(
                        [$row['student']->name, $row['student']->email, $row['student']->phone],
                        $courses->flatMap(fn ($c) => [$row['cells'][$c->id]['enrollment'] ? $row['cells'][$c->id]['progress']->percent : '', $row['cells'][$c->id]['label']])->all(),
                        [$row['last_active']?->format('Y-m-d H:i') ?? '', implode('; ', $row['reasons'])],
                    ));
                }
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Across the class's courses: one row per student with a cell per course (that course's row),
     * the latest activity in any of them, and every reason to help, named by course.
     */
    private function overview(Classroom $classroom, Collection $courses): array
    {
        $perCourse = $courses->mapWithKeys(fn (Course $course) => [$course->id => $this->courseRows($classroom, $course)['rows']->keyBy(fn ($row) => $row['student']->id)]);
        $several = $courses->count() > 1;

        $rows = $classroom->students()->get()->map(function (User $student) use ($courses, $perCourse, $several) {
            $cells = $courses->mapWithKeys(fn (Course $course) => [$course->id => $perCourse[$course->id][$student->id]]);
            $statuses = $cells->pluck('status');
            $reasons = $cells->filter(fn ($cell) => $cell['reason'] && $cell['reason'] !== self::ACCOUNT_OFF)
                ->map(fn ($cell, $courseId) => ($several ? $courses->firstWhere('id', $courseId)->title.': ' : '').$cell['reason'])->values()->all();
            if ($cells->contains(fn ($cell) => $cell['reason'] === self::ACCOUNT_OFF)) {
                array_unshift($reasons, self::ACCOUNT_OFF); // said once, not once per course
            }
            $groups = array_values(array_filter([
                $reasons ? 'help' : null,
                $statuses->intersect(['learning', 'retake'])->isNotEmpty() ? 'learning' : null,
                $statuses->every(fn ($s) => $s === 'completed') ? 'completed' : null,
                $statuses->every(fn ($s) => in_array($s, ['not_started', 'no_access', 'ended'], true)) && $statuses->contains('not_started') ? 'not_started' : null,
                $cells->contains(fn ($cell) => in_array('no_access', $cell['groups'], true)) ? 'no_access' : null,
            ]));
            $percents = $cells->filter(fn ($cell) => $cell['enrollment'])->map(fn ($cell) => $cell['progress']->percent);

            return [
                'student' => $student,
                'cells' => $cells,
                'reasons' => $reasons,
                'groups' => $groups,
                'last_active' => $cells->pluck('last_active')->filter()->max(),
                'percent' => $percents->isEmpty() ? null : (int) round($percents->avg()),
            ];
        });

        $rows = $rows->sortBy([
            fn ($a, $b) => in_array('help', $b['groups'], true) <=> in_array('help', $a['groups'], true),
            fn ($a, $b) => ($a['percent'] ?? -1) <=> ($b['percent'] ?? -1),
            fn ($a, $b) => strcmp($a['student']->name, $b['student']->name),
        ])->values();
        $cells = $rows->flatMap(fn ($row) => $row['cells']->values());

        return [
            'rows' => $rows,
            'average' => $cells->filter(fn ($cell) => $cell['enrollment'])->avg(fn ($cell) => $cell['progress']->percent),
            'completions' => $cells->where('status', 'completed')->count(),
            'pairs' => $cells->count(),
        ];
    }

    /**
     * One course of the class: one row per student with the course gradebook row (progress, best
     * score per test), when they last finished something, and which groups they fall in (one row
     * can be in "Needs help" and "Learning"). Students without the course get a row too.
     */
    private function courseRows(Classroom $classroom, Course $course): array
    {
        $students = $classroom->students()->get();
        $book = ResultController::gradebook($course, $students->pluck('id')->all());
        $byUser = $book['rows']->keyBy(fn ($row) => $row['enrollment']->user_id);
        $quietSince = now()->subDays(self::QUIET_DAYS);

        $rows = $students->map(function (User $student) use ($byUser, $quietSince) {
            $row = $byUser[$student->id] ?? null;
            if (! $row || $row['status'] === 'ended' && ! $row['enrollment']->completed_at) {
                return $this->row($student, $row, $row ? 'Access ended' : 'No access', ['no_access', 'help'], null, $row ? 'Access ended' : 'Cannot open the course');
            }

            $lastActive = $row['progress']->lastActivityAt();
            $quiet = $row['status'] !== 'completed' && (! $lastActive || $lastActive->lt($quietSince))
                && $row['enrollment']->starts_at->lt($quietSince); // just joined: give them time
            $groups = [$row['status'] === 'retake' ? 'learning' : $row['status']];
            $reason = null;
            if ($student->isDisabled() && $row['status'] !== 'completed') {
                $reason = self::ACCOUNT_OFF; // nothing else matters until it is turned back on
                $groups[] = 'no_access';
            } elseif ($row['status'] === 'retake') {
                $reason = 'Did not pass: '.$row['retake']->pluck('title')->implode(', ');
            } elseif ($row['status'] === 'not_started' && $quiet) {
                $reason = 'Has not started';
            } elseif ($quiet && $row['status'] === 'learning') {
                $reason = 'No activity for '.($lastActive ? (int) $lastActive->diffInDays(now()).' days' : 'a while');
            }
            if ($reason) {
                $groups[] = 'help';
            }

            return $this->row($student, $row, ResultController::COURSE_STATUSES[$row['status']], $groups, $lastActive, $reason);
        });

        // Needs help first, then furthest behind.
        $rows = $rows->sortBy([
            fn ($a, $b) => in_array('help', $b['groups'], true) <=> in_array('help', $a['groups'], true),
            fn ($a, $b) => ($a['enrollment'] ? $a['progress']->percent : -1) <=> ($b['enrollment'] ? $b['progress']->percent : -1),
            fn ($a, $b) => strcmp($a['student']->name, $b['student']->name),
        ])->values();
        $enrolled = $rows->filter(fn ($row) => $row['enrollment']);

        return [
            'tests' => $book['tests'],
            'labels' => $book['labels'],
            'rows' => $rows,
            'average' => $enrolled->isEmpty() ? null : $enrolled->avg(fn ($row) => $row['progress']->percent),
            'completions' => $rows->where('status', 'completed')->count(),
            'pairs' => $rows->count(),
        ];
    }

    private function row(User $student, ?array $book, string $label, array $groups, $lastActive, ?string $reason): array
    {
        return [
            'student' => $student,
            'enrollment' => $book['enrollment'] ?? null,
            'progress' => $book['progress'] ?? null,
            'scores' => $book['scores'] ?? collect(),
            'retake' => $book['retake'] ?? collect(),
            'asked' => $book['asked'] ?? false,
            'status' => $book['status'] ?? 'no_access',
            'label' => $label,
            'groups' => $groups,
            'last_active' => $lastActive,
            'reason' => $reason,
        ];
    }

    /** The form's fields (one left empty or not sent is cleared), and the chosen courses in order. */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'courses' => ['required', 'array', 'max:30'],
            'courses.*' => ['integer', 'exists:courses,id'],
            'teacher' => ['nullable', 'string', 'max:120'],
            'schedule' => ['nullable', 'string', 'max:190'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'courses.required' => 'Tick at least one course.',
            'ends_on.after_or_equal' => 'The last day cannot be before the first day.',
        ]);
        $courseIds = $data['courses'];
        unset($data['courses']);

        return [$data + array_fill_keys(['teacher', 'schedule', 'starts_on', 'ends_on', 'notes'], null), $courseIds];
    }
}
