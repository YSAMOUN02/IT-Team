{!! $chatMessage->body !!}

@if (filled($chatMessage->signature))
--
{!! $chatMessage->signature !!}

@endif
{!! __('account.chat_email_footer') !!} {!! rtrim((string) config('app.public_url'), '/').route('account.chat', [], false) !!}
