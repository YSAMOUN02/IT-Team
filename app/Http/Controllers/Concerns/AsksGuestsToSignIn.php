<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Course;
use App\Models\CoursePlan;
use Illuminate\Http\Request;

/**
 * A visitor who is not signed in and clicks Buy / Enrol sees what they chose
 * next to "Create account" and "Sign in" (and Google), then comes straight
 * back to this page with the same plan.
 */
trait AsksGuestsToSignIn
{
    protected function askToSignIn(Request $request, Course $course, ?CoursePlan $plan = null)
    {
        $request->session()->put('url.intended', $request->fullUrl());

        return response()->view('auth.continue', [
            'course' => $course->loadCount('lessons'),
            'plan' => $plan,
            'tab' => $request->query('tab') === 'login' || old('form') === 'login' ? 'login' : 'register',
        ]);
    }
}
