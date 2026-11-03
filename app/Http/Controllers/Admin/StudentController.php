<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CourseAccess;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Support\CourseProgress;
use App\Support\DeviceLimit;
use Illuminate\Http\Request;

/** Students, their course access and progress. Access can also be given by hand (e.g. cash payment). */
class StudentController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.students.index', [
            'students' => User::where('role', 'student')
                ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($q) => $q
                    ->where('name', 'like', "%$term%")->orWhere('email', 'like', "%$term%")->orWhere('phone', 'like', "%$term%")))
                // "current": can open at least one course right now; "off": the account or one of its courses is turned off.
                ->when($request->query('show') === 'current', fn ($q) => $q->whereNull('disabled_at')->whereHas('enrollments', fn ($e) => $e->active()))
                ->when($request->query('show') === 'off', fn ($q) => $q->where(fn ($q) => $q
                    ->whereNotNull('disabled_at')->orWhereHas('enrollments', fn ($e) => $e->whereNotNull('disabled_at'))))
                ->with(['enrollments' => fn ($q) => $q->with('course:id,title')->latest()])
                ->withCount(['orders' => fn ($q) => $q->where('status', 'paid')])
                ->latest()->paginate(25)->withQueryString(),
        ]);
    }

    /** Add a student by hand (e.g. signed up at the office). Admin accounts stay under Admin > Users. */
    public function create()
    {
        return view('admin.students.create', ['courses' => Course::with('plans')->orderBy('title')->get()]);
    }

    public function store(Request $request)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['nullable', 'string', 'min:8', 'max:190'],
            'course_id' => ['nullable', 'exists:courses,id'],
            'days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'payment_method' => ['nullable', 'in:'.implode(',', array_keys(\App\Models\Invoice::METHODS))],
            'invoice_note' => ['nullable', 'string', 'max:190'],
        ], ['email.unique' => 'A student or admin already uses this email.']);

        $course = Course::find($data['course_id'] ?? null);
        $lifetime = $request->boolean('lifetime');
        // Account and course together, or neither: no half-made account whose password nobody saw.
        [$student, $password] = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $course, $lifetime) {
            [$student, $password] = User::createStudent($data['name'], $data['email'], $data['phone'] ?? null, $data['password'] ?? null);
            if ($course) {
                $days = $course->manualAccessDays($data['days'] ?? null, $lifetime);
                \App\Models\Invoice::forManualAccess(Enrollment::grant($student, $course, $days, 'manual'), $days, (float) ($data['amount'] ?? 0), $data['payment_method'] ?? null, $data['invoice_note'] ?? null);
            }

            return [$student, $password];
        });
        $mailed = $request->boolean('send_email') ? CourseAccess::sendTo($student, $course, $password) : null;

        return redirect()->route('admin.students.show', $student)
            ->with('status', "Student {$student->name} added.".CourseAccess::note($mailed))
            ->with('new_student', ['name' => $student->name, 'email' => $student->email, 'password' => $password]);
    }

    public function invoice(\App\Models\Invoice $invoice)
    {
        return view('invoices.show', ['invoice' => $invoice, 'back' => route('admin.students.show', $invoice->user_id)]);
    }

    public function show(User $user)
    {
        abort_if($user->isAdmin(), 404);

        return view('admin.students.show', [
            'student' => $user,
            'enrollments' => $user->enrollments()->with('course')->latest()->get()
                ->filter(fn ($e) => $e->course)
                ->map(fn ($e) => ['enrollment' => $e->setRelation('user', $user), 'progress' => CourseProgress::for($user, $e->course)]),
            'orders' => $user->orders()->latest()->get(),
            'attempts' => QuizAttempt::where('user_id', $user->id)->with('quiz.course:id,title')->latest('started_at')->latest('id')->take(10)->get(),
            'attemptCount' => QuizAttempt::where('user_id', $user->id)->count(),
            'invoices' => $user->invoices()->latest('issued_at')->latest('id')->get(),
            'courses' => Course::with('plans')->orderBy('title')->get(),
        ]);
    }

    public function grant(Request $request, User $user)
    {
        abort_if($user->isAdmin(), 404);
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'payment_method' => ['nullable', 'in:'.implode(',', array_keys(\App\Models\Invoice::METHODS))],
            'invoice_note' => ['nullable', 'string', 'max:190'],
        ]);

        $course = Course::findOrFail($data['course_id']);
        $days = $course->manualAccessDays($data['days'] ?? null, $request->boolean('lifetime'));
        \App\Models\Invoice::forManualAccess(Enrollment::grant($user, $course, $days, 'manual'), $days, (float) ($data['amount'] ?? 0), $data['payment_method'] ?? null, $data['invoice_note'] ?? null);
        $mailed = $request->boolean('send_email') ? CourseAccess::sendTo($user, $course) : null;

        return back()->with('status', "Access to {$course->title} given.".CourseAccess::note($mailed));
    }

    public function revoke(Enrollment $enrollment)
    {
        $enrollment->delete();

        return back()->with('status', 'Access removed.');
    }

    /** Turn the whole account off (signed out, cannot sign in or open any course) or back on. */
    public function update(Request $request, User $user)
    {
        abort_if($user->isAdmin(), 404);
        $off = $request->boolean('disabled');

        $user->forceFill(['disabled_at' => $off ? ($user->disabled_at ?? now()) : null])->save();
        if ($off) {
            DeviceLimit::clear($user);
        }

        return back()->with('status', $off
            ? "{$user->name}'s account is turned off. They are signed out and cannot sign in."
            : "{$user->name}'s account is on again.");
    }

    /**
     * Give a certificate by hand, e.g. for a class at the office. A student with no record for
     * the course gets a "certificate only" one: the certificate, without access to the course.
     */
    public function giveCertificate(Request $request, User $user)
    {
        abort_if($user->isAdmin(), 404);
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'issued_on' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $course = Course::findOrFail($data['course_id']);
        $enrollment = Enrollment::firstOrNew(['user_id' => $user->id, 'course_id' => $course->id]);
        if ($enrollment->certificate_code) {
            return back()->withErrors(['course_id' => "{$user->name} already has a certificate for {$course->title} ({$enrollment->certificate_code})."]);
        }
        if (! $enrollment->exists) {
            $enrollment->forceFill(['source' => 'certificate', 'starts_at' => now(), 'expires_at' => now()]);
        }

        $enrollment->forceFill([
            'completed_at' => $request->date('issued_on')?->setTimeFrom(now()) ?? $enrollment->completed_at ?? now(),
            'certificate_code' => CourseProgress::newCertificateCode(),
        ])->save();

        return back()->with('status', "Certificate {$enrollment->certificate_code} given to {$user->name} for {$course->title}.");
    }

    /**
     * The code stops working. A student who really finished every lesson and test stays
     * "completed" (so the certificate is not issued again by itself); otherwise completion is undone too.
     */
    public function takeBackCertificate(Enrollment $enrollment)
    {
        $enrollment->load('user', 'course');
        $code = $enrollment->certificate_code;

        if ($enrollment->source === 'certificate') {
            $enrollment->delete(); // it only existed for the certificate
        } else {
            $finished = CourseProgress::for($enrollment->user, $enrollment->course)->complete;
            $enrollment->forceFill(['certificate_code' => null, 'completed_at' => $finished ? $enrollment->completed_at : null])->save();
        }

        return back()->with('status', "Certificate $code taken back from {$enrollment->user->name}.");
    }

    /** Turn one course off for the student (progress and dates are kept) or back on. */
    public function updateEnrollment(Request $request, Enrollment $enrollment)
    {
        $off = $request->boolean('disabled');
        $enrollment->forceFill(['disabled_at' => $off ? ($enrollment->disabled_at ?? now()) : null])->save();
        $enrollment->load('user', 'course');

        return back()->with('status', $off
            ? "{$enrollment->course->title} is turned off for {$enrollment->user->name}."
            : "{$enrollment->course->title} is on again for {$enrollment->user->name}.");
    }

    /** Signs the student out on every device (e.g. they shared their password). */
    public function signOut(User $user)
    {
        abort_if($user->isAdmin(), 404);
        DeviceLimit::clear($user);

        return back()->with('status', "{$user->name} has been signed out on every device.");
    }
}
