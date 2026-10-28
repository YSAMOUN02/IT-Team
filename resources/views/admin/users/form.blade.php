@extends('admin.layout')

@section('title', $user->exists ? ($user->is(auth()->user()) ? 'My account' : $user->name) : 'New user')
@section('crumbs')
    <span aria-hidden="true">/</span><a href="{{ route('admin.users.index') }}">Users</a>
    <span aria-hidden="true">/</span><span>{{ $user->exists ? 'Edit' : 'New' }}</span>
@endsection

@section('content')
    <form method="post" data-main-form action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
        @csrf
        @if ($user->exists) @method('PUT') @endif

        <div class="form-grid reveal">
            <div>
                <section class="card">
                    <div class="card-head"><h2>Profile</h2></div>
                    <div class="card-body fields">
                        <div class="cols-2">
                            @include('admin.fields.input', ['name' => 'name', 'label' => 'Name', 'value' => $user->name, 'required' => true])
                            @include('admin.fields.input', ['name' => 'username', 'label' => 'Username', 'value' => $user->username, 'help' => 'Optional. Letters, numbers, - and _. Can be used instead of the email to sign in.', 'attrs' => 'autocapitalize="none" spellcheck="false"'])
                        </div>
                        @include('admin.fields.input', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $user->email, 'required' => true, 'help' => 'Also works for signing in.'])
                    </div>
                </section>
                <section class="card">
                    <div class="card-head"><div><h2>Password</h2><p>{{ $user->exists ? 'Leave empty to keep the current password.' : 'At least 8 characters.' }}</p></div></div>
                    <div class="card-body fields">
                        <div class="cols-2">
                            @include('admin.fields.input', ['name' => 'password', 'label' => 'New password', 'type' => 'password', 'value' => '', 'required' => ! $user->exists, 'attrs' => 'autocomplete="new-password"'])
                            @include('admin.fields.input', ['name' => 'password_confirmation', 'label' => 'Repeat password', 'type' => 'password', 'value' => '', 'attrs' => 'autocomplete="new-password"'])
                        </div>
                    </div>
                </section>
            </div>
            <div class="form-side">
                <section class="card">
                    <div class="card-head"><h2>Save</h2></div>
                    <div class="card-foot save-bar">
                        <button type="submit" class="btn btn-primary btn-block">@include('admin.partials.icon', ['name' => 'check']) Save user</button>
                    </div>
                </section>
            </div>
        </div>
    </form>
@endsection
