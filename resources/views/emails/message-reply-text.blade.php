{!! $reply->body !!}

@if (filled($reply->signature))
--
{!! $reply->signature !!}

@endif
{!! __('account.reply_quote_head', ['date' => $reply->inquiry->created_at->format('d/m/Y H:i'), 'name' => $reply->inquiry->name]) !!}
> {!! $reply->inquiry->subject !!}
@foreach (preg_split('/\R/u', (string) $reply->inquiry->message) as $line)
> {!! $line !!}
@endforeach
