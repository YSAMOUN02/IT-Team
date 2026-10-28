<?php

namespace App\Http\Controllers;

use App\Support\CourseProgress;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** The student's own area: courses, orders, profile. */
class AccountController extends Controller
{
    public function courses(Request $request)
    {
        $user = $request->user();
        $enrollments = $user->enrollments()->with('course')->latest('starts_at')->get()
            ->filter(fn ($e) => $e->course)
            ->map(function ($e) use ($user) {
                $progress = CourseProgress::for($user, $e->course);

                return [
                    'enrollment' => $e,
                    'course' => $e->course,
                    'progress' => $progress,
                    'next' => $e->isActive() && ! $progress->complete ? $progress->nextItem() : null,
                    'lastActive' => $progress->lastActivityAt(), // null = not studied yet
                ];
            })
            // Courses still being learned first, the most recently studied (or bought) on top.
            ->sortBy(fn ($row) => [
                $row['enrollment']->isActive() && ! $row['progress']->complete ? 0 : 1,
                -($row['lastActive'] ?? $row['enrollment']->starts_at)?->timestamp,
            ])
            ->values();

        return view('account.courses', [
            'enrollments' => $enrollments,
            'featured' => $enrollments->first(fn ($row) => $row['next'] !== null),
            'stats' => [
                'courses' => $enrollments->count(),
                'completed' => $enrollments->filter(fn ($row) => $row['progress']->complete)->count(),
                'lessons' => $enrollments->sum(fn ($row) => count($row['progress']->completedLessonIds)),
                'certificates' => $enrollments->filter(fn ($row) => $row['enrollment']->certificate_code)->count(),
            ],
            'pendingOrders' => $user->orders()->where('status', 'pending')->where('created_at', '>', now()->subDay())->get(),
        ]);
    }

    public function orders(Request $request)
    {
        return view('account.orders', ['orders' => $request->user()->orders()->latest()->get()]);
    }

    public function profile(Request $request)
    {
        return view('account.profile', ['user' => $request->user()]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            // Students who signed up with Google have no password yet: they can set one without it.
            'current_password' => $user->password ? ['nullable', 'required_with:password', 'current_password'] : ['nullable'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        $user->fill(collect($data)->only('name', 'email', 'phone')->all());
        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }
        $user->save();

        return back()->with('status', __('account.profile_saved'));
    }
}
