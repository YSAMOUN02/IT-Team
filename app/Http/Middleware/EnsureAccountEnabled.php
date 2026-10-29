<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Signs a student out as soon as an admin turns their account off (Admin > Students > Disable account). */
class EnsureAccountEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isDisabled()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => __('account.account_disabled')], 403)
                : redirect()->route('login')->withErrors(['email' => __('account.account_disabled')]);
        }

        return $next($request);
    }
}
