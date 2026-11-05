@extends('admin.layout')

@section('title', $inquiry->subject)
@section('crumbs')
    <span aria-hidden="true">/</span><a href="{{ route('admin.inquiries.index') }}">Messages</a>
    <span aria-hidden="true">/</span><span>Message</span>
@endsection
@section('actions')
    <a class="btn btn-primary" href="#reply" data-reply-jump>@include('admin.partials.icon', ['name' => 'mail']) Reply</a>
    <form method="post" action="{{ route('admin.inquiries.destroy', $inquiry) }}" data-confirm="Delete this message from {{ $inquiry->name }}{{ $inquiry->replies->isNotEmpty() ? ' and the replies sent to it' : '' }}?">
        @csrf @method('DELETE')
        <button class="btn btn-danger">@include('admin.partials.icon', ['name' => 'trash']) Delete</button>
    </form>
@endsection

@php
    $initials = fn (?string $name) => mb_strtoupper(collect(preg_split('/\s+/u', trim((string) $name)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('')) ?: '?';
@endphp

@section('content')
    <div class="form-grid reveal">
        <div class="thread-col">
            {{-- The conversation: their message, then each reply sent from here --}}
            <section class="card">
                <div class="card-head"><div><h2>Conversation</h2><p>{{ $inquiry->replies->count() ? $inquiry->replies->count().' '.str('reply')->plural($inquiry->replies->count()).' sent' : 'Not answered yet' }}</p></div></div>
                <div class="card-body thread">
                    <article class="bubble bubble-in">
                        <header>
                            <span class="avatar" aria-hidden="true">{{ $initials($inquiry->name) }}</span>
                            <div><b>{{ $inquiry->name }}</b><small>{{ $inquiry->email }} · {{ $inquiry->created_at->format('d M Y, H:i') }} ({{ $inquiry->created_at->diffForHumans() }})</small></div>
                        </header>
                        <p class="bubble-subject">{{ $inquiry->subject }}</p>
                        <div class="message-body">{{ $inquiry->message }}</div>
                    </article>

                    @foreach ($inquiry->replies as $sent)
                        <article class="bubble bubble-out">
                            <header>
                                <span class="avatar avatar-us" aria-hidden="true">{{ $initials($sent->user?->name ?? 'TEAMIT') }}</span>
                                <div><b>{{ $sent->user?->name ?? 'Admin' }}</b><small>to {{ $sent->to_email }} · {{ $sent->created_at->format('d M Y, H:i') }} ({{ $sent->created_at->diffForHumans() }})</small></div>
                                <span class="status status-on">Sent</span>
                            </header>
                            <p class="bubble-subject">{{ $sent->subject }}</p>
                            <div class="message-body">{{ $sent->body }}</div>
                            @if ($sent->signature)
                                <div class="bubble-signature">{{ $sent->signature }}</div>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>

            {{-- Reply --}}
            <section class="card" id="reply">
                <div class="card-head"><div><h2>Reply by email</h2><p>Sent from {{ setting('site_name', config('app.name')) }} &lt;{{ $from }}&gt;. Their answer comes back to that inbox.</p></div></div>
                <form class="card-body fields reply-form" method="post" action="{{ route('admin.inquiries.reply', $inquiry) }}" data-reply-form>
                    @csrf
                    @unless ($mailReady)
                        <div class="alert alert-error" role="status">@include('admin.partials.icon', ['name' => 'alert']) Email is not set up on this server yet (MAIL_* in .env): replies cannot be sent.</div>
                    @endunless
                    @if (session('reply_error'))
                        <div class="alert alert-error" role="alert">@include('admin.partials.icon', ['name' => 'alert']) {{ session('reply_error') }}</div>
                    @endif

                    <div class="reply-to">
                        <span class="reply-to-label">To</span>
                        <span class="reply-chip"><span class="avatar" aria-hidden="true">{{ $initials($inquiry->name) }}</span><span class="reply-chip-text"><b>{{ $inquiry->name }}</b> <small>{{ $inquiry->email }}</small></span></span>
                    </div>

                    @include('admin.fields.input', ['name' => 'subject', 'label' => 'Subject', 'value' => str_starts_with(mb_strtolower($inquiry->subject), 're:') ? $inquiry->subject : 'Re: '.$inquiry->subject, 'required' => true, 'max' => 200])

                    @php $bodyError = $errors->first('body'); @endphp
                    <div @class(['field', 'has-error' => $bodyError])>
                        <label class="field-label" for="f-body"><span>Message<em>*</em></span></label>
                        <textarea id="f-body" name="body" rows="9" required placeholder="Write your reply…" data-reply-body>{{ old('body') }}</textarea>
                        @if ($bodyError)<span class="error-text">{{ $bodyError }}</span>@endif
                    </div>

                    @include('admin.partials.signature-box')

                    <div class="reply-actions">
                        <span class="help">Ctrl + Enter sends.</span>
                        <button class="btn btn-primary" @disabled(! $mailReady)>@include('admin.partials.icon', ['name' => 'mail']) Send reply</button>
                    </div>
                </form>
            </section>
        </div>

        <div class="form-side">
            <section class="card">
                <div class="card-head"><h2>Contact</h2></div>
                <div class="card-body">
                    <dl class="meta-list">
                        <dt>Email</dt><dd><a href="mailto:{{ $inquiry->email }}">{{ $inquiry->email }}</a></dd>
                        @if ($inquiry->phone)
                            <dt>Phone</dt><dd><a href="tel:{{ preg_replace('/[^\d+]/', '', $inquiry->phone) }}">{{ $inquiry->phone }}</a></dd>
                        @endif
                        <dt>Received</dt><dd>{{ $inquiry->created_at->format('d M Y, H:i') }}</dd>
                        <dt>Read</dt><dd>{{ $inquiry->read_at?->format('d M Y, H:i') ?? '—' }}</dd>
                        <dt>Replied</dt><dd>{{ $inquiry->replied_at?->format('d M Y, H:i') ?? 'Not yet' }}</dd>
                    </dl>
                </div>
                @if ($inquiry->phone)
                    <div class="card-foot"><a class="btn btn-ghost btn-block" href="tel:{{ preg_replace('/[^\d+]/', '', $inquiry->phone) }}">@include('admin.partials.icon', ['name' => 'phone']) Call {{ $inquiry->phone }}</a></div>
                @endif
            </section>
        </div>
    </div>
@endsection
