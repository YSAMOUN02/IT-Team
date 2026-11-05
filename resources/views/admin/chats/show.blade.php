@extends('admin.layout')

@section('title', $student->name)
@section('subtitle', $student->email.($student->phone ? ' · '.$student->phone : ''))
@section('crumbs')
    <span aria-hidden="true">/</span><a href="{{ route('admin.chats.index') }}">Chat</a>
    <span aria-hidden="true">/</span><span>Conversation</span>
@endsection
@section('actions')
    <a class="btn btn-ghost" href="{{ route('admin.students.show', $student) }}">@include('admin.partials.icon', ['name' => 'user']) Student page</a>
@endsection

@section('content')
    <div class="form-grid reveal">
        <div>
            <section class="card chat-card" data-chat data-poll-url="{{ route('admin.chats.poll', $student) }}">
                <div class="chat-thread" data-chat-thread aria-live="polite">
                    @if ($messages->isEmpty())
                        <p class="chat-empty" data-chat-empty>No messages yet. Write the first one below: {{ $student->name }} sees it in My account › Chat.</p>
                    @endif
                    @include('partials.chat-messages', ['chatMessages' => $messages, 'chatViewer' => 'admin', 'chatStudent' => $student])
                </div>

                <div class="chat-tabs">
                    <nav class="segmented" aria-label="Send by">
                        <a href="{{ route('admin.chats.show', $student) }}" data-chat-tab="chat" @class(['on' => $mode === 'chat'])>Chat</a>
                        <a href="{{ route('admin.chats.show', [$student, 'via' => 'email']) }}" data-chat-tab="email" @class(['on' => $mode === 'email'])>Email</a>
                    </nav>
                </div>

                {{-- Chat: they see it on the website, with a red count on their account menu. --}}
                <div data-chat-pane="chat" @if ($mode !== 'chat') hidden @endif>
                    <form class="chat-compose" method="post" action="{{ route('admin.chats.store', $student) }}" data-chat-form data-error-text="The message was not sent. Check your connection and try again.">
                        @csrf
                        <input type="hidden" name="after" value="{{ $messages->last()?->id ?? 0 }}" data-chat-after>
                        <textarea name="body" rows="1" maxlength="5000" required aria-label="Message" placeholder="Write a message…" data-chat-body>{{ old('via') === 'email' ? '' : old('body') }}</textarea>
                        <button class="btn btn-primary" type="submit">@include('admin.partials.icon', ['name' => 'chat']) Send</button>
                    </form>
                    <p class="chat-error" role="alert" data-chat-error @if (old('via') === 'email' || ! $errors->has('body')) hidden @endif>{{ old('via') === 'email' ? '' : $errors->first('body') }}</p>
                    <p class="chat-hint">Enter sends. Shift + Enter makes a new line. {{ $student->name }} sees it on the website.</p>
                </div>

                {{-- Email: goes to their inbox from the site's address, and stays in this history too. --}}
                <div data-chat-pane="email" @if ($mode !== 'email') hidden @endif>
                    <form class="card-body fields reply-form" method="post" action="{{ route('admin.chats.store', $student) }}" data-reply-form>
                        @csrf
                        <input type="hidden" name="via" value="email">
                        @unless ($mailReady)
                            <div class="alert alert-error" role="status">@include('admin.partials.icon', ['name' => 'alert']) Email is not set up on this server yet (MAIL_* in .env): emails cannot be sent.</div>
                        @endunless
                        @if (session('chat_error'))
                            <div class="alert alert-error" role="alert">@include('admin.partials.icon', ['name' => 'alert']) {{ session('chat_error') }}</div>
                        @endif

                        <div class="reply-to">
                            <span class="reply-to-label">To</span>
                            <span class="reply-chip"><span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($student->name, 0, 1)) }}</span><span class="reply-chip-text"><b>{{ $student->name }}</b> <small>{{ $student->email }}</small></span></span>
                        </div>

                        @include('admin.fields.input', ['name' => 'subject', 'label' => 'Subject', 'value' => '', 'required' => true, 'max' => 200])

                        @php $emailError = old('via') === 'email' ? $errors->first('body') : null; @endphp
                        <div @class(['field', 'has-error' => $emailError])>
                            <label class="field-label" for="f-email-body"><span>Message<em>*</em></span></label>
                            <textarea id="f-email-body" name="body" rows="9" required placeholder="Write your email…" data-reply-body>{{ old('via') === 'email' ? old('body') : '' }}</textarea>
                            @if ($emailError)<span class="error-text">{{ $emailError }}</span>@endif
                        </div>

                        @include('admin.partials.signature-box')

                        <div class="reply-actions">
                            <span class="help">Sent from {{ setting('site_name', config('app.name')) }} &lt;{{ $from }}&gt;. Ctrl + Enter sends.</span>
                            <button class="btn btn-primary" @disabled(! $mailReady)>@include('admin.partials.icon', ['name' => 'mail']) Send email</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <div class="form-side">
            <section class="card">
                <div class="card-head"><h2>Student</h2></div>
                <div class="card-body">
                    <dl class="meta-list">
                        <dt>Email</dt><dd><a href="mailto:{{ $student->email }}" title="Open in your own mail app">{{ $student->email }}</a></dd>
                        @if ($student->phone)<dt>Phone</dt><dd><a href="tel:{{ $student->phone }}">{{ $student->phone }}</a></dd>@endif
                        <dt>Joined</dt><dd>{{ $student->created_at->format('d M Y') }}</dd>
                        <dt>Courses</dt>
                        <dd>
                            @forelse ($courses as $enrollment)
                                <a class="chat-side-line" href="{{ route('admin.courses.students', $enrollment->course_id) }}">{{ $enrollment->course?->title }}</a>
                            @empty
                                <span class="muted">None yet</span>
                            @endforelse
                        </dd>
                        @if ($classes->isNotEmpty())
                            <dt>Classes</dt>
                            <dd>@include('admin.classes._chips', ['chipClasses' => $classes])</dd>
                        @endif
                    </dl>
                </div>
            </section>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/chat.js') }}?v={{ @filemtime(public_path('js/chat.js')) }}" defer></script>
@endpush
