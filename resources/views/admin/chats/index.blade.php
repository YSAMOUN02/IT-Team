@extends('admin.layout')

@section('title', 'Chat')
@section('subtitle', 'Conversations with students. They write from My account › Chat; answer here by chat or by email.')
@section('crumbs')<span aria-hidden="true">/</span><span>Overview</span><span aria-hidden="true">/</span><span>Chat</span>@endsection
@section('actions')
    <a class="btn btn-ghost" href="{{ route('admin.students.index') }}">@include('admin.partials.icon', ['name' => 'users']) Write to a student</a>
@endsection

@section('content')
    <div class="card reveal">
        <form class="toolbar" method="get" action="{{ route('admin.chats.index') }}">
            <nav class="segmented" aria-label="Show">
                <a href="{{ route('admin.chats.index', array_filter(['q' => $term])) }}" @class(['on' => $show === 'all'])>All <span class="muted mono">{{ $counts['all'] }}</span></a>
                <a href="{{ route('admin.chats.index', array_filter(['show' => 'unread', 'q' => $term])) }}" @class(['on' => $show === 'unread', 'seg-alert' => $counts['unread']])>Unread <span class="muted mono">{{ $counts['unread'] }}</span></a>
            </nav>
            @if ($show === 'unread')<input type="hidden" name="show" value="unread">@endif
            <div class="search">
                @include('admin.partials.icon', ['name' => 'search'])
                <input type="search" name="q" value="{{ $term }}" placeholder="Name, email or phone…" aria-label="Search conversations">
            </div>
        </form>

        @if ($conversations->isEmpty())
            <div class="empty-state">
                <div class="ico">@include('admin.partials.icon', ['name' => 'chat'])</div>
                @if ($term !== '' || $show === 'unread')
                    <h3>Nothing here</h3>
                    <p>No conversation matches. <a href="{{ route('admin.chats.index') }}">Show all</a></p>
                @else
                    <h3>No conversations yet</h3>
                    <p>Students start one from My account › Chat. To write first, open a student's page or a class and press Chat.</p>
                @endif
            </div>
        @else
            <div class="table-wrap">
                <table class="data chat-inbox">
                    <thead><tr><th>Student</th><th class="num hide-sm">Last message</th></tr></thead>
                    <tbody>
                    @foreach ($conversations as $student)
                        @php
                            $last = $lastMessages[$student->id] ?? null;
                            $by = ! $last || $last->from_student ? '' : ((int) $last->sender_id === (int) auth()->id() ? 'You: ' : ($last->sender?->name ?: 'Admin').': ');
                            $text = $last?->emailed_at ? 'Email: '.$last->subject : $last?->body;
                        @endphp
                        <tr @class(['unread' => $student->unread_count])>
                            <td>
                                <div class="row-title">
                                    <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($student->name, 0, 1)) }}</span>
                                    <div>
                                        <a href="{{ route('admin.chats.show', $student) }}">{{ $student->name }}</a>
                                        @if ($student->unread_count)<b class="chat-count" title="Not read yet">{{ $student->unread_count }}</b>@endif
                                        <span class="row-sub">{{ $by }}{{ \Illuminate\Support\Str::limit((string) $text, 90) }}</span>
                                        <span class="row-sub show-sm">{{ $last?->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="num hide-sm"><span title="{{ $last?->created_at->format('d M Y, H:i') }}">{{ $last?->created_at->diffForHumans() }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $conversations->links('admin.pager') }}
        @endif
    </div>
@endsection
