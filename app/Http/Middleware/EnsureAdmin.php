<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/** Only admins may use the admin panel; students are sent to their courses. */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            return redirect()->route('account.courses');
        }

        // The admin is written in English, so "5 minutes ago" should be too (the site itself is Khmer).
        Carbon::setLocale('en');

        return $next($request);
    }
}
