@extends('admin.layout')

@section('title', 'Students')
@section('subtitle', 'Student accounts, with their courses and payments. Admin accounts are under Admin users.')
@section('crumbs')<span aria-hidden="true">/</span><span>Sales</span><span aria-hidden="true">/</span><span>Students</span>@endsection

@section('actions')
    <a class="btn btn-primary" href="{{ route('admin.students.create') }}">@include('admin.partials.icon', ['name' => 'plus']) Add student</a>
@endsection

@section('content')
    <div class="card reveal">
        <form class="toolbar" method="get">
            <div class="search">
                @include('admin.partials.icon', ['name' => 'search'])
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Name, email or phone…" aria-label="Search students">
            </div>
            <select name="show" aria-label="Show" onchange="this.form.submit()">
                <option value="">All students</option>
                <option value="current" @selected(request('show') === 'current')>Studying now</option>
                <option value="off" @selected(request('show') === 'off')>Turned off</option>
            </select>
            <span class="toolbar-count">{{ $students->total() }} students</span>
        </form>
        @if ($students->isEmpty())
            <div class="empty-state">
                <div class="ico">@include('admin.partials.icon', ['name' => 'users'])</div>
                <h3>No students {{ request('q') || request('show') ? 'match' : 'yet' }}</h3>
                <p>Students appear here when they create an account, buy a course or are added with “Add student”.</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Student</th><th class="hide-sm">Phone</th><th class="hide-sm">Courses</th><th class="hide-sm">Paid orders</th><th class="hide-sm">Joined</th></tr></thead>
                    <tbody>
                    @foreach ($students as $student)
                        @php
                            // Green: can open it now · red: turned off by an admin · grey: access ended.
                            $chips = $student->enrollments->filter(fn ($e) => $e->course)->map(fn ($enrollment) => [...match (true) {
                                (bool) $enrollment->disabled_at => ['status-blocked', 'Turned off '.$enrollment->disabled_at->format('d M Y')],
                                $enrollment->source === 'certificate' => ['status-off', 'Certificate only'],
                                ! $enrollment->isActive() => ['status-off', 'Ended '.$enrollment->expires_at?->format('d M Y')],
                                default => ['status-on', $enrollment->expires_at ? 'Until '.$enrollment->expires_at->format('d M Y') : 'Lifetime'],
                            }, $enrollment->course->title]);
                        @endphp
                        <tr>
                            <td>
                                <div class="row-title">
                                    <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($student->name, 0, 1)) }}</span>
                                    <div>
                                        <a href="{{ route('admin.students.show', $student) }}">{{ $student->name }}</a>
                                        @if ($student->disabled_at)<span class="status status-blocked">Account off</span>@endif
                                        <span class="row-sub">{{ $student->email }}@if ($student->phone)<span class="show-sm"> · {{ $student->phone }}</span>@endif</span>
                                        {{-- Phones: the courses sit under the name instead of in their own column. --}}
                                        @if ($chips->isNotEmpty())
                                            <div class="course-chips show-sm-flex">
                                                @foreach ($chips as [$chipClass, $when, $title])
                                                    <span class="status {{ $chipClass }}" title="{{ $when }}">{{ $title }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="num hide-sm">{{ $student->phone ?: '—' }}</td>
                            <td class="hide-sm">
                                <div class="course-chips">
                                    @forelse ($chips as [$chipClass, $when, $title])
                                        <span class="status {{ $chipClass }}" title="{{ $when }}">{{ $title }}</span>
                                    @empty
                                        <span class="muted">—</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="num hide-sm">{{ $student->orders_count }}</td>
                            <td class="num hide-sm">{{ $student->created_at->format('d M Y') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $students->links('admin.pager') }}
        @endif
    </div>
@endsection
