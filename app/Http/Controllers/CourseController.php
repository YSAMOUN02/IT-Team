<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AsksGuestsToSignIn;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Page;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    use AsksGuestsToSignIn;

    public function index()
    {
        return view('courses.index', [
            'page' => Page::findBySlug('courses'),
            'courses' => Course::published()->get(),
        ]);
    }

    /**
     * Join a free course: only an account is needed (guests are sent to sign in /
     * register first and come back here), no payment and no QR code.
     */
    public function enroll(Request $request, Course $course)
    {
        abort_unless($course->is_published, 404);
        if (! $course->isFree()) {
            return redirect()->route('checkout.show', $course->slug);
        }

        $user = $request->user();
        if (! $user) {
            return $this->askToSignIn($request, $course);
        }
        if ($user->courseAccessOff($course)) {
            return redirect()->route('courses.show', $course->slug)->with('checkout_notice', __('account.access_off'));
        }
        if (! $user->canAccessCourse($course)) {
            Enrollment::grant($user, $course, null, 'free');
        }

        return redirect()->route('learn.course', $course->slug)->with('status', __('account.enrolled_free', ['course' => $course->title]));
    }

    public function show(Request $request, Course $course)
    {
        abort_unless($course->is_published || $request->user()?->isAdmin(), 404);
        // Admins can check the page exactly as a visitor who has not bought the course sees it.
        $user = $request->user()?->isAdmin() && $request->query('as') === 'visitor' ? null : $request->user();

        return view('courses.show', [
            'course' => $course,
            'outline' => $course->curriculum(),
            'enrollment' => $user?->enrollments()->where('course_id', $course->id)->first(),
            'hasAccess' => $user?->canAccessCourse($course) ?? false,
            // Periods and prices always show; if online payment is not set up yet, checkout says so.
            'canBuy' => $course->isPurchasable(),
            'others' => Course::published()->whereKeyNot($course->id)->get(),
        ]);
    }
}
