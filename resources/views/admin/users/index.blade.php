@extends('admin.layout')

@section('title', 'Users')
@section('subtitle', 'People who can sign in to this admin. Students are under Students.')
@section('crumbs')<span aria-hidden="true">/</span><span>System</span><span aria-hidden="true">/</span><span>Users</span>@endsection
@section('actions')
    <a class="btn btn-primary" href="{{ route('admin.users.create') }}">@include('admin.partials.icon', ['name' => 'plus']) New user</a>
@endsection

@section('content')
    <div class="card reveal">
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Name</th><th>Email</th><th>Since</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>
                            <div class="row-title">
                                <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                                <a href="{{ route('admin.users.edit', $user) }}">{{ $user->name }}</a>
                                @if ($user->is(auth()->user()))<span class="status status-on">You</span>@endif
                            </div>
                        </td>
                        <td class="mono">{{ $user->email }}</td>
                        <td class="num">{{ $user->created_at?->format('d M Y') }}</td>
                        <td class="actions">
                            <a class="btn btn-ghost btn-sm" href="{{ route('admin.users.edit', $user) }}">@include('admin.partials.icon', ['name' => 'edit']) Edit</a>
                            @unless ($user->is(auth()->user()))
                                <form method="post" action="{{ route('admin.users.destroy', $user) }}" data-confirm="Remove {{ $user->name }}? They will no longer be able to sign in.">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm btn-icon" title="Delete" aria-label="Delete">@include('admin.partials.icon', ['name' => 'trash'])</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
