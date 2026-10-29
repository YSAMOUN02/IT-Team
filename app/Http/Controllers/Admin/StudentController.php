<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
            'courses' => Course::orderBy('title')->get(),
        ]);
    }

    public function grant(Request $request, User $user)
    {
        abort_if($user->isAdmin(), 404);
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $course = Course::findOrFail($data['course_id']);
        Enrollment::grant($user, $course, $data['days'] ?? null, 'manual');

        return back()->with('status', "Access to {$course->title} given.");
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
