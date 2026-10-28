<?php

namespace App\Http\Middleware;

use App\Support\DeviceLimit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Signs a student out of this browser once they have signed in on too many others. */
class EnsureKnownDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (DeviceLimit::applies($user)) {
            $token = DeviceLimit::tokenFor($user, $request->session()->get(DeviceLimit::SESSION_KEY));

            if ($token === null) {
                DeviceLimit::register($user); // signed in before the limit was switched on
            } elseif (! DeviceLimit::isKnown($user, $token)) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return $request->expectsJson()
                    ? response()->json(['message' => __('account.signed_out_elsewhere')], 401)
                    : redirect()->route('login')->with('status', __('account.signed_out_elsewhere'));
            }
        }

        return $next($request);
    }
}
