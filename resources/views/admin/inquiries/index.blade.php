@extends('admin.layout')

@section('title', 'Messages')
@section('subtitle', 'Sent by visitors from the contact form and course pages.')
@section('crumbs')<span aria-hidden="true">/</span><span>Overview</span><span aria-hidden="true">/</span><span>Messages</span>@endsection

@section('content')
    <div class="card reveal">
        @if ($inquiries->isEmpty())
            <div class="empty-state">
                <div class="ico">@include('admin.partials.icon', ['name' => 'inbox'])</div>
                <h3>Inbox is empty</h3>
                <p>Messages appear here when visitors use the contact form.</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Subject</th><th>From</th><th>Received</th><th><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                    @foreach ($inquiries as $inquiry)
                        <tr @class(['unread' => ! $inquiry->read_at])>
                            <td>
                                <div class="row-title">
                                    <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($inquiry->name, 0, 1)) }}</span>
                                    <div>
                                        <a href="{{ route('admin.inquiries.show', $inquiry) }}">{{ $inquiry->subject }}</a>
                                        <span class="row-sub">{{ \Illuminate\Support\Str::limit($inquiry->message, 90) }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $inquiry->name }}<span class="row-sub">{{ $inquiry->email }}</span></td>
                            <td class="num">{{ $inquiry->created_at->format('d M Y, H:i') }}</td>
                            <td class="actions">
                                <a class="btn btn-ghost btn-sm" href="{{ route('admin.inquiries.show', $inquiry) }}">Open</a>
                                <form method="post" action="{{ route('admin.inquiries.destroy', $inquiry) }}" data-confirm="Delete this message from {{ $inquiry->name }}?">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm btn-icon" title="Delete" aria-label="Delete">@include('admin.partials.icon', ['name' => 'trash'])</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $inquiries->links('admin.pager') }}
        @endif
    </div>
@endsection
