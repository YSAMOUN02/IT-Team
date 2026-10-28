<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function create()
    {
        return view('admin.auth.login');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string'],
        ]);

        // Email or username, any letter case.
        $login = mb_strtolower(trim($data['login']));
        $user = User::where('role', 'admin')
            ->where(fn ($q) => $q->whereRaw('lower(email) = ?', [$login])->orWhereRaw('lower(username) = ?', [$login]))
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['login' => 'These details do not match an admin account.']);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
