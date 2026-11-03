@php
    // Written like a personal email (not a newsletter): the reply, the signature, then the original message.
    $inquiry = $reply->inquiry;
    $font = "'Kantumruy Pro','Noto Sans Khmer','Khmer OS System','Khmer UI','Segoe UI',Roboto,Helvetica,Arial,sans-serif";
    $site = rtrim((string) config('app.public_url'), '/');
    $logoPath = setting('logo') ?: setting('favicon');
    $logo = $logoPath ? (preg_match('#^https?://#', $logoPath) ? $logoPath : $site.'/storage/'.ltrim($logoPath, '/')) : null;
    $html = fn (?string $text) => \App\Mail\MessageReply::textToHtml($text);
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $reply->subject }}</title>
<link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700&display=swap" rel="stylesheet">
<!--[if mso]><style>body, table, td, p, a, span { font-family: 'Khmer UI', 'Segoe UI', Arial, sans-serif !important; }</style><![endif]-->
</head>
<body style="margin:0;padding:0;background:#ffffff;font-family:{!! $font !!};color:#111827">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-family:{!! $font !!}">
<tr><td style="padding:28px 20px;font-family:{!! $font !!}">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;font-family:{!! $font !!}">

    {{-- The reply --}}
    <tr><td style="font-size:15.5px;line-height:1.75;color:#111827;font-family:{!! $font !!}">{!! $html($reply->body) !!}</td></tr>

    {{-- Signature --}}
    @if (filled($reply->signature))
        <tr><td style="padding-top:22px;font-family:{!! $font !!}">
            <table role="presentation" cellpadding="0" cellspacing="0" style="border-top:2px solid #5f59f7;font-family:{!! $font !!}">
                <tr>
                    @if ($logo)
                        <td valign="top" style="padding:14px 14px 0 0"><img src="{{ $logo }}" width="44" height="44" alt="" style="display:block;border-radius:50%;border:0"></td>
                    @endif
                    <td valign="top" style="padding-top:12px;font-size:13.5px;line-height:1.65;color:#4f5775;font-family:{!! $font !!}">{!! $html($reply->signature) !!}</td>
                </tr>
            </table>
        </td></tr>
    @endif

    {{-- Their message, quoted --}}
    <tr><td style="padding-top:30px;font-family:{!! $font !!}">
        <p style="margin:0 0 8px;font-size:12.5px;color:#8b91aa;font-family:{!! $font !!}">
            {{ __('account.reply_quote_head', ['date' => $inquiry->created_at->format('d/m/Y H:i'), 'name' => $inquiry->name]) }}
        </p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr><td style="border-left:3px solid #e4e6ef;padding:4px 0 4px 14px;font-size:13.5px;line-height:1.7;color:#5c6b8d;font-family:{!! $font !!}">
                <b style="color:#4f5775">{{ $inquiry->subject }}</b><br>
                {!! $html($inquiry->message) !!}
            </td></tr>
        </table>
    </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
