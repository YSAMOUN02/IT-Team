<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
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
                ->withCount(['enrollments', 'orders' => fn ($q) => $q->where('status', 'paid')])
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
                ->map(fn ($e) => ['enrollment' => $e, 'progress' => CourseProgress::for($user, $e->course)]),
            'orders' => $user->orders()->latest()->get(),
            'courses' => Course::orderBy('title')->get(),
        ]);
    }

    public function grant(Request $request, User $user)
    {
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

    /** Signs the student out on every device (e.g. they shared their password). */
    public function signOut(User $user)
    {
        abort_if($user->isAdmin(), 404);
        DeviceLimit::clear($user);

        return back()->with('status', "{$user->name} has been signed out on every device.");
    }
}
