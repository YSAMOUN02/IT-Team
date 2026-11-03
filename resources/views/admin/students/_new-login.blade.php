{{-- Shown once after an admin creates a student account: the sign-in to pass on. --}}
@if ($new = session('new_student'))
    <section class="card new-login reveal">
        <div class="card-head"><div><h2>Sign-in for {{ $new['name'] }}</h2><p>Shown only now: send it to the student. They can change the password after signing in.</p></div></div>
        <div class="card-body">
            <dl class="new-login-list">
                <dt>Website</dt><dd class="mono">{{ route('login') }}</dd>
                <dt>Email</dt><dd class="mono">{{ $new['email'] }}</dd>
                <dt>Password</dt><dd class="mono">{{ $new['password'] }}</dd>
            </dl>
        </div>
    </section>
@endif
