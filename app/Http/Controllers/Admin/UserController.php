<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Admin accounts only. Students (role "student") are managed under Admin > Students. */
class UserController extends Controller
{
    public function index()
    {
        return view('admin.users.index', ['users' => User::where('role', 'admin')->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User]);
    }

    public function store(Request $request)
    {
        // Without this the column default makes the new account a student, who cannot open the admin.
        $user = new User;
        $user->role = 'admin';

        return $this->save($request, $user);
    }

    public function edit(User $user)
    {
        abort_unless($user->isAdmin(), 404);

        return view('admin.users.form', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        abort_unless($user->isAdmin(), 404);

        return $this->save($request, $user);
    }

    public function destroy(Request $request, User $user)
    {
        abort_unless($user->isAdmin(), 404);
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'User deleted.');
    }

    private function save(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['nullable', 'alpha_dash', 'max:60', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'email', 'max:190', Rule::unique('users')->ignore($user->id)],
            'password' => [$user->exists ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ]);

        $user->name = $data['name'];
        $user->username = $data['username'] ?? null;
        $user->email = $data['email'];
        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }
        $user->save();

        return redirect()->route('admin.users.index')->with('status', 'User saved.');
    }
}
