@extends('admin.layout')

@section('title', 'Students')
@section('subtitle', 'Accounts created on the website, with their courses and payments.')
@section('crumbs')<span aria-hidden="true">/</span><span>Sales</span><span aria-hidden="true">/</span><span>Students</span>@endsection

@section('content')
    <div class="card reveal">
        <form class="toolbar" method="get">
            <div class="search">
                @include('admin.partials.icon', ['name' => 'search'])
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Name, email or phone…" aria-label="Search students">
            </div>
            <span class="toolbar-count">{{ $students->total() }} students</span>
        </form>
        @if ($students->isEmpty())
            <div class="empty-state">
                <div class="ico">@include('admin.partials.icon', ['name' => 'users'])</div>
                <h3>No students {{ request('q') ? 'match' : 'yet' }}</h3>
                <p>Students appear here when they create an account or buy a course.</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Student</th><th>Phone</th><th>Courses</th><th>Paid orders</th><th>Joined</th></tr></thead>
                    <tbody>
                    @foreach ($students as $student)
                        <tr>
                            <td>
                                <div class="row-title">
                                    <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($student->name, 0, 1)) }}</span>
                                    <div><a href="{{ route('admin.students.show', $student) }}">{{ $student->name }}</a><span class="row-sub">{{ $student->email }}</span></div>
                                </div>
                            </td>
                            <td class="num">{{ $student->phone ?: '—' }}</td>
                            <td class="num">{{ $student->enrollments_count }}</td>
                            <td class="num">{{ $student->orders_count }}</td>
                            <td class="num">{{ $student->created_at->format('d M Y') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $students->links('admin.pager') }}
        @endif
    </div>
@endsection
