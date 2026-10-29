@extends('admin.layout')

@php
    $tones = ['pending' => 'status-sched', 'approved' => 'status-on', 'declined' => 'status-blocked'];
@endphp

@section('title', 'Re-exam requests')
@section('subtitle', 'Students who used every try at a test without passing ask for another. Approve with more tries, or decline.')
@section('crumbs')<span aria-hidden="true">/</span><span>Sales</span><span aria-hidden="true">/</span><span>Re-exam requests</span>@endsection

@section('content')
<div class="card reveal">
    <div class="toolbar">
        <nav class="segmented" aria-label="Show">
            @foreach (\App\Models\RetakeRequest::STATUSES as $key => $label)
                <a href="{{ route('admin.retake-requests.index', ['status' => $key]) }}" @class(['on' => $status === $key])>{{ $label }} <span class="muted mono">{{ $counts[$key] ?? 0 }}</span></a>
            @endforeach
            <a href="{{ route('admin.retake-requests.index', ['status' => 'all']) }}" @class(['on' => $status === 'all'])>All <span class="muted mono">{{ $counts->sum() }}</span></a>
        </nav>
        <span class="toolbar-count">{{ $requests->total() }} requests</span>
    </div>

    @if ($requests->isEmpty())
        <div class="empty-state">
            <div class="ico">@include('admin.partials.icon', ['name' => 'clock'])</div>
            <h3>{{ $status === 'pending' ? 'No one is waiting' : 'No requests here' }}</h3>
            <p>When a student uses every try at a test without passing, they can ask for another. Their request shows here.</p>
        </div>
    @else
        <ul class="retake-list">
            @foreach ($requests as $retake)
                @php
                    $done = $tries[$retake->user_id.'-'.$retake->quiz_id] ?? collect();
                    $best = $done->first();
                @endphp
                <li class="retake-item">
                    <div class="retake-who">
                        <div class="row-title">
                            <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($retake->user->name, 0, 1)) }}</span>
                            <div>
                                <a href="{{ route('admin.students.show', $retake->user) }}">{{ $retake->user->name }}</a>
                                <span class="row-sub">{{ $retake->user->email }}</span>
                            </div>
                        </div>
                        <p class="retake-test">
                            <b class="qtype qtype-{{ $retake->quiz->type }}">{{ \App\Models\Quiz::TYPES[$retake->quiz->type] }}</b>
                            <a href="{{ route('admin.results.index', ['quiz' => $retake->quiz_id, 'q' => $retake->user->email]) }}">{{ $retake->quiz->title }}</a>
                            <span class="muted">· {{ $retake->quiz->course?->title }}</span>
                        </p>
                        <p class="retake-tries">
                            {{ $done->count() }} {{ $done->count() === 1 ? 'try' : 'tries' }} used
                            @if ($best) · best <a href="{{ route('admin.results.show', $best) }}"><b>{{ $best->percent }}%</b></a> @endif
                            · pass mark {{ $retake->quiz->pass_percent }}% · asked {{ $retake->created_at->diffForHumans() }}
                        </p>
                        @if ($retake->message)
                            <blockquote class="retake-message">“{{ $retake->message }}”</blockquote>
                        @endif
                    </div>

                    <div class="retake-answer">
                        @if ($retake->isPending())
                            <form method="post" action="{{ route('admin.retake-requests.decide', $retake) }}" class="fields">
                                @csrf @method('PATCH')
                                <div class="field">
                                    <label class="field-label" for="r-{{ $retake->id }}"><span>Note to the student (optional)</span></label>
                                    <textarea id="r-{{ $retake->id }}" name="reply" rows="2" maxlength="1000" placeholder="e.g. Read module 3 again first."></textarea>
                                </div>
                                <div class="retake-buttons">
                                    <select name="extra_attempts" aria-label="Tries to give">
                                        @foreach ([1, 2, 3] as $n)
                                            <option value="{{ $n }}">{{ $n }} more {{ $n === 1 ? 'try' : 'tries' }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-primary btn-sm" name="decision" value="approve">@include('admin.partials.icon', ['name' => 'check']) Approve</button>
                                    <button class="btn btn-danger btn-sm" name="decision" value="decline">Decline</button>
                                </div>
                            </form>
                        @else
                            <span class="status {{ $tones[$retake->status] }}">{{ \App\Models\RetakeRequest::STATUSES[$retake->status] }}{{ $retake->status === 'approved' ? ' · '.$retake->extra_attempts.' more '.($retake->extra_attempts === 1 ? 'try' : 'tries') : '' }}</span>
                            <p class="retake-tries">by {{ $retake->decider?->name ?? 'an admin' }}, {{ $retake->decided_at?->format('d M Y, H:i') }}</p>
                            @if ($retake->reply)<blockquote class="retake-message">“{{ $retake->reply }}”</blockquote>@endif
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
        {{ $requests->links('admin.pager') }}
    @endif
</div>
@endsection
