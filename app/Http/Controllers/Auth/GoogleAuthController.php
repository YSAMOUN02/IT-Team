<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * "Continue with Google" for students: signs in an existing account (found by
 * Google ID or by the same verified email) or creates a new student account.
 * Admin accounts keep signing in with their password only.
 */
class GoogleAuthController extends Controller
{
    public static function enabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirect()
    {
        abort_unless(self::enabled(), 404);

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request)
    {
        abort_unless(self::enabled(), 404);

        if ($request->filled('error')) { // the visitor pressed Cancel on Google's page
            return redirect()->route('login')->with('status', __('account.google_cancelled'));
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')->withErrors(['email' => __('account.google_failed')]);
        }

        $email = Str::lower(trim((string) $google->getEmail()));
        $verified = filter_var($google->user['email_verified'] ?? $google->user['verified_email'] ?? false, FILTER_VALIDATE_BOOL);
        if ($email === '' || ! $verified) {
            return redirect()->route('login')->withErrors(['email' => __('account.google_unverified')]);
        }

        $user = User::where('google_id', $google->getId())->first()
            ?? User::whereRaw('lower(email) = ?', [$email])->first();

        if ($user?->isAdmin()) {
            return redirect()->route('login')->withErrors(['email' => __('account.google_admin')]);
        }

        $isNew = ! $user;
        $user ??= new User(['name' => $google->getName() ?: Str::before($email, '@'), 'email' => $email]);
        $user->forceFill([
            'google_id' => $google->getId(),
            'avatar' => $google->getAvatar(),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'role' => $user->role ?? 'student',
        ])->save();

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('account.courses'))
            ->with('status', $isNew ? __('account.welcome', ['name' => $user->name]) : null);
    }
}
