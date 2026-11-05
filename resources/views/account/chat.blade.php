@extends('layouts.site')

@section('title', __('account.chat_title'))
@section('no_ads', true)

@section('content')
    <section class="account-head">
        <div class="container">
            <p class="eyebrow">{{ auth()->user()->name }}</p>
            <h1>{{ __('account.chat_title') }}</h1>
            @include('account._tabs')
        </div>
    </section>

    <section class="section section-flush-top">
        <div class="container">
            <div class="chat-card" data-chat data-poll-url="{{ route('account.chat.poll') }}">
                <div class="chat-head">
                    @if (setting('favicon'))<img src="{{ media_url(setting('favicon')) }}" alt="">@endif
                    <div>
                        <strong>{{ setting('site_name', config('app.name')) }}</strong>
                        <small>{{ __('account.chat_intro') }}</small>
                    </div>
                </div>
                <div class="chat-thread" data-chat-thread aria-live="polite">
                    @if ($messages->isEmpty())
                        <p class="chat-empty" data-chat-empty>{{ __('account.chat_empty') }}</p>
                    @endif
                    @include('partials.chat-messages', ['chatMessages' => $messages, 'chatViewer' => 'student', 'chatStudent' => auth()->user()])
                </div>
                <form class="chat-compose" method="post" action="{{ route('account.chat.store') }}" data-chat-form data-error-text="{{ __('account.chat_error') }}">
                    @csrf
                    <input type="hidden" name="after" value="{{ $messages->last()?->id ?? 0 }}" data-chat-after>
                    <textarea name="body" rows="1" maxlength="2000" required aria-label="{{ __('account.chat_message') }}" placeholder="{{ __('account.chat_placeholder') }}" data-chat-body>{{ old('body') }}</textarea>
                    <button class="btn btn-primary" type="submit">{{ __('account.chat_send') }}</button>
                </form>
                <p class="chat-error" role="alert" data-chat-error @unless ($errors->has('body')) hidden @endunless>{{ $errors->first('body') }}</p>
                <p class="chat-hint">{{ __('account.chat_hint') }}</p>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/chat.js') }}?v={{ @filemtime(public_path('js/chat.js')) }}" defer></script>
@endpush
