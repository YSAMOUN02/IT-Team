<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Student sign-in, registration and sign-out on the public site. */
class StudentAuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attemptWhen($credentials, fn (User $user) => ! $user->isDisabled(), $request->boolean('remember'))) {
            // Right password, but an admin turned the account off (said only once the password is right).
            $user = Auth::getLastAttempted();
            if ($user?->isDisabled() && Auth::getProvider()->validateCredentials($user, $credentials)) {
                throw ValidationException::withMessages(['email' => __('account.account_disabled')]);
            }

            // Signed up with Google and never set a password: point them at the Google button.
            $googleOnly = GoogleAuthController::enabled()
                && User::where('email', $credentials['email'])->whereNull('password')->whereNotNull('google_id')->exists();

            throw ValidationException::withMessages(['email' => __($googleOnly ? 'account.use_google' : 'account.login_failed')]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('account.courses'));
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = new User(collect($data)->only('name', 'email', 'phone', 'password')->all());
        $user->role = 'student';
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('account.courses'))->with('status', __('account.welcome', ['name' => $user->name]));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
