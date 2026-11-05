@php
    // Written like a personal email (not a newsletter), as the replies in Admin > Messages are: the text, the signature, a link to the chat.
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
<title>{{ $chatMessage->subject }}</title>
<link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700&display=swap" rel="stylesheet">
<!--[if mso]><style>body, table, td, p, a, span { font-family: 'Khmer UI', 'Segoe UI', Arial, sans-serif !important; }</style><![endif]-->
</head>
<body style="margin:0;padding:0;background:#ffffff;font-family:{!! $font !!};color:#111827">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-family:{!! $font !!}">
<tr><td style="padding:28px 20px;font-family:{!! $font !!}">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;font-family:{!! $font !!}">

    <tr><td style="font-size:15.5px;line-height:1.75;color:#111827;font-family:{!! $font !!}">{!! $html($chatMessage->body) !!}</td></tr>

    @include('emails._signature', ['signatureText' => $chatMessage->signature])

    <tr><td style="padding-top:26px;font-size:13px;line-height:1.7;color:#8b91aa;font-family:{!! $font !!}">
        {{ __('account.chat_email_footer') }}
        <a href="{{ $site.route('account.chat', [], false) }}" style="color:#5f59f7">{{ __('account.chat_email_link') }}</a>
    </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
