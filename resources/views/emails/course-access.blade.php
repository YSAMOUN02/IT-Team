@php
    // Links and pictures point at the live site (PUBLIC_URL), not at the address the admin used.
    $site = rtrim((string) config('app.public_url'), '/');
    $link = fn (string $name, $params = []) => $site.route($name, $params, false);
    $asset = fn (?string $path) => blank($path) ? null : (preg_match('#^https?://#', $path) ? $path : $site.'/storage/'.ltrim($path, '/'));

    // Kantumruy Pro for everything (it has English letters too). Mail apps that load web fonts use it; the rest use the next ones.
    // Gmail and Outlook do not load web fonts: Khmer then falls to the phone or PC sans-serif Khmer fonts listed after them
    // (Noto Sans Khmer on Android / Gmail, Khmer UI on Windows), never to a serif one.
    $font = "'Kantumruy Pro','Noto Sans Khmer','Khmer OS System','Khmer UI','Leelawadee UI','Segoe UI',Roboto,Helvetica,Arial,sans-serif";
    $ink = '#111827'; $ink2 = '#5c6b8d'; $accent = '#343090'; $accent2 = '#5f59f7';

    $name = setting('site_name', config('app.name'));
    $logo = $asset(setting('logo') ?: setting('favicon'));
    $enrollment = $course ? $student->enrollments()->where('course_id', $course->id)->first() : null;
    $cover = $course ? $asset($course->image) : null;
    $lessons = $course ? $course->lessons()->count() : 0;
    $days = $enrollment?->expires_at ? max(1, (int) ceil(now()->diffInDays($enrollment->expires_at))) : null;
    $phone = setting('phone');
    $email = setting('email');
    $socials = array_filter(['Telegram' => setting('telegram_url'), 'Facebook' => setting('facebook_url'), 'YouTube' => setting('youtube_url')]);
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $course ? __('account.mail_access_subject', ['course' => $course->title]) : __('account.mail_welcome_subject') }}</title>
<link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    @import url('https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;500;600;700&display=swap');
    body, table, td, a, p, h1, h2 { font-family: {!! $font !!}; }
    a.btn:hover { background: {{ $accent }} !important; }
    @media (max-width: 620px) {
        .wrap { padding: 12px !important; }
        .pad { padding-left: 22px !important; padding-right: 22px !important; }
        .h1 { font-size: 24px !important; }
        .chip { display: block !important; margin: 0 0 6px !important; }
    }
</style>
<!--[if mso]><style>body, table, td, p, a, span, h1, h2 { font-family: 'Khmer UI', 'Segoe UI', Arial, sans-serif !important; }</style><![endif]-->
</head>
<body style="margin:0;padding:0;background:#eef0fb;font-family:{!! $font !!};color:{{ $ink }};-webkit-font-smoothing:antialiased">
{{-- Inbox preview line --}}
<div style="display:none;max-height:0;overflow:hidden;opacity:0">{{ $course ? __('account.mail_access_text', ['course' => $course->title]) : __('account.mail_welcome_text') }}</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef0fb;font-family:{!! $font !!}">
<tr><td class="wrap" align="center" style="padding:32px 16px;font-family:{!! $font !!};color:#111827">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px">

    {{-- Brand --}}
    <tr><td align="center" style="padding:0 0 18px;font-family:{!! $font !!}">
        <a href="{{ $site }}" style="text-decoration:none;color:{{ $accent }};font-size:20px;font-weight:700;font-family:{!! $font !!}">
            @if ($logo)<img src="{{ $logo }}" width="40" height="40" alt="" style="vertical-align:middle;border-radius:50%;margin-right:10px;border:0">@endif
            <span style="vertical-align:middle;font-family:{!! $font !!}">{{ setting('logo_text', 'TEAMIT') }} <span style="color:{{ $accent2 }};font-family:{!! $font !!}">{{ setting('logo_text_accent', 'Cambodia') }}</span></span>
        </a>
    </td></tr>

    <tr><td style="background:#ffffff;border-radius:22px;overflow:hidden;box-shadow:0 12px 40px rgba(52,48,144,.12);font-family:{!! $font !!}">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">

        {{-- Hero --}}
        <tr><td class="pad" style="background:{{ $accent }};background-image:linear-gradient(135deg,{{ $accent }} 0%,{{ $accent2 }} 100%);padding:38px 40px 34px;color:#ffffff;font-family:{!! $font !!}">
            <p style="margin:0 0 10px;font-size:13px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#d9d7ff;font-family:{!! $font !!}">{{ __('account.mail_hello', ['name' => $student->name]) }}</p>
            <h1 class="h1" style="margin:0 0 12px;font-size:28px;line-height:1.35;font-weight:700;color:#ffffff;font-family:{!! $font !!}">
                {{ $password ? __('account.mail_heading_new') : __('account.mail_heading_access') }}
            </h1>
            <p style="margin:0;font-size:15px;line-height:1.75;color:#e6e5ff;font-family:{!! $font !!}">
                @if ($password){{ __('account.mail_welcome_text') }} @endif
                @if ($course){{ __('account.mail_access_text', ['course' => $course->title]) }}@endif
            </p>
        </td></tr>

        {{-- Course card --}}
        @if ($course)
            <tr><td class="pad" style="padding:30px 40px 6px;font-family:{!! $font !!}">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e4e6f2;border-radius:16px;overflow:hidden">
                    @if ($cover)
                        <tr><td><img src="{{ $cover }}" width="518" alt="{{ $course->title }}" style="display:block;width:100%;max-height:220px;object-fit:cover;border:0"></td></tr>
                    @endif
                    <tr><td style="padding:20px 22px 22px;font-family:{!! $font !!}">
                        <p style="margin:0 0 6px;font-size:12px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:{{ $accent2 }};font-family:{!! $font !!}">{{ __('account.mail_course_label') }}</p>
                        <h2 style="margin:0 0 14px;font-size:20px;line-height:1.4;font-weight:700;color:{{ $ink }};font-family:{!! $font !!}">{{ $course->title }}</h2>
                        <span class="chip" style="display:inline-block;margin:0 6px 6px 0;padding:7px 13px;border-radius:999px;background:#efeeff;color:{{ $accent }};font-size:13px;font-weight:600;font-family:{!! $font !!}">
                            ⏳ {{ __('account.mail_duration') }}: {{ $days ? __('account.mail_days', ['days' => $days]) : __('account.mail_lifetime') }}
                        </span>
                        @if ($enrollment?->expires_at)
                            <span class="chip" style="display:inline-block;margin:0 6px 6px 0;padding:7px 13px;border-radius:999px;background:#efeeff;color:{{ $accent }};font-size:13px;font-weight:600;font-family:{!! $font !!}">
                                📅 {{ __('account.mail_until', ['date' => $enrollment->expires_at->format('d/m/Y')]) }}
                            </span>
                        @endif
                        @if ($lessons)
                            <span class="chip" style="display:inline-block;margin:0 6px 6px 0;padding:7px 13px;border-radius:999px;background:#efeeff;color:{{ $accent }};font-size:13px;font-weight:600;font-family:{!! $font !!}">
                                📘 {{ __('account.mail_lessons', ['count' => $lessons]) }}
                            </span>
                        @endif
                    </td></tr>
                </table>
            </td></tr>
        @endif

        {{-- Sign-in --}}
        <tr><td class="pad" style="padding:24px 40px 4px;font-family:{!! $font !!}">
            @if ($password)
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f7ff;border:1.5px dashed #c9c6fb;border-radius:16px">
                    <tr><td style="padding:20px 22px 8px;font-family:{!! $font !!}">
                        <p style="margin:0;font-size:15px;font-weight:700;color:{{ $ink }};font-family:{!! $font !!}">🔑 {{ __('account.mail_login_title') }}</p>
                    </td></tr>
                    <tr><td style="padding:4px 22px 18px;font-family:{!! $font !!}">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px">
                            <tr>
                                <td style="padding:9px 0;color:{{ $ink2 }};border-bottom:1px solid #e7e6fb;font-family:{!! $font !!}">{{ __('account.mail_login_page') }}</td>
                                <td align="right" style="padding:9px 0 9px 12px;border-bottom:1px solid #e7e6fb;word-break:break-all;font-family:{!! $font !!}"><a href="{{ $link('login') }}" style="color:{{ $accent2 }};font-weight:600;text-decoration:none;font-family:{!! $font !!}">{{ preg_replace('#^https?://#', '', $link('login')) }}</a></td>
                            </tr>
                            <tr>
                                <td style="padding:9px 0;color:{{ $ink2 }};border-bottom:1px solid #e7e6fb;font-family:{!! $font !!}">{{ __('account.mail_email') }}</td>
                                <td align="right" style="padding:9px 0 9px 12px;border-bottom:1px solid #e7e6fb;font-weight:600;color:{{ $ink }};word-break:break-all;font-family:{!! $font !!}">{{ $student->email }}</td>
                            </tr>
                            <tr>
                                <td style="padding:12px 0 2px;color:{{ $ink2 }};font-family:{!! $font !!}">{{ __('account.mail_password') }}</td>
                                <td align="right" style="padding:12px 0 2px;font-family:{!! $font !!}">
                                    <span style="display:inline-block;padding:7px 14px;border-radius:10px;background:#ffffff;border:1px solid #d9d7ff;font-family:'SFMono-Regular',Consolas,'Courier New',monospace;font-size:16px;font-weight:700;letter-spacing:.06em;color:{{ $accent }}">{{ $password }}</span>
                                </td>
                            </tr>
                        </table>
                    </td></tr>
                </table>
                <p style="margin:10px 2px 0;font-size:12.5px;line-height:1.6;color:{{ $ink2 }};font-family:{!! $font !!}">🔒 {{ __('account.mail_change_password') }}</p>
            @else
                <p style="margin:0;font-size:14px;line-height:1.7;color:{{ $ink2 }};font-family:{!! $font !!}">
                    {{ __('account.mail_existing_login', ['email' => $student->email]) }}
                    <a href="{{ $link('login') }}" style="color:{{ $accent2 }};font-weight:600;text-decoration:none;font-family:{!! $font !!}">{{ preg_replace('#^https?://#', '', $link('login')) }}</a>
                </p>
            @endif
        </td></tr>

        {{-- Button --}}
        <tr><td class="pad" align="center" style="padding:26px 40px 8px;font-family:{!! $font !!}">
            <a class="btn" href="{{ $course ? $link('courses.show', $course) : $link('account.courses') }}"
               style="display:inline-block;background:{{ $accent2 }};background-image:linear-gradient(135deg,{{ $accent2 }},{{ $accent }});color:#ffffff;text-decoration:none;padding:15px 38px;border-radius:999px;font-size:16px;font-weight:700;box-shadow:0 8px 22px rgba(95,89,247,.35);font-family:{!! $font !!}">
                {{ __('account.start_learning') }} &rarr;
            </a>
        </td></tr>

        {{-- 3 steps, for a new account --}}
        @if ($password)
            <tr><td class="pad" style="padding:22px 40px 6px;font-family:{!! $font !!}">
                <p style="margin:0 0 12px;font-size:15px;font-weight:700;color:{{ $ink }};font-family:{!! $font !!}">{{ __('account.mail_steps_title') }}</p>
                @foreach ([__('account.mail_step1'), __('account.mail_step2'), __('account.mail_step3')] as $i => $step)
                    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 10px">
                        <tr>
                            <td valign="top" style="width:30px;font-family:{!! $font !!}">
                                <span style="display:inline-block;width:24px;height:24px;line-height:24px;border-radius:50%;background:{{ $accent2 }};color:#ffffff;text-align:center;font-size:12px;font-weight:700;font-family:{!! $font !!}">{{ $i + 1 }}</span>
                            </td>
                            <td style="font-size:14px;line-height:24px;color:{{ $ink }};font-family:{!! $font !!}">{{ $step }}</td>
                        </tr>
                    </table>
                @endforeach
            </td></tr>
        @endif

        {{-- Help --}}
        <tr><td class="pad" style="padding:20px 40px 32px;font-family:{!! $font !!}">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #eceef6">
                <tr><td style="padding-top:18px;font-size:14px;line-height:1.8;color:{{ $ink2 }};font-family:{!! $font !!}">
                    <b style="color:{{ $ink }};font-family:{!! $font !!}">{{ __('account.mail_help') }}</b><br>
                    @if ($phone)📞 <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" style="color:{{ $ink2 }};text-decoration:none;font-family:{!! $font !!}">{{ $phone }}</a>&nbsp;&nbsp; @endif
                    @if ($email)✉️ <a href="mailto:{{ $email }}" style="color:{{ $ink2 }};text-decoration:none;font-family:{!! $font !!}">{{ $email }}</a>@endif
                </td></tr>
            </table>
        </td></tr>

    </table>
    </td></tr>

    {{-- Footer --}}
    <tr><td align="center" style="padding:22px 20px 0;font-size:12px;line-height:1.7;color:#8a93ad;font-family:{!! $font !!}">
        @if ($socials)
            <p style="margin:0 0 8px;font-family:{!! $font !!}">
                @foreach ($socials as $label => $url)
                    <a href="{{ $url }}" style="color:{{ $accent2 }};text-decoration:none;font-weight:600;font-family:{!! $font !!}">{{ $label }}</a>@if (! $loop->last) &nbsp;·&nbsp; @endif
                @endforeach
            </p>
        @endif
        <p style="margin:0 0 4px;font-family:{!! $font !!}">{{ __('account.mail_footer_note') }}</p>
        <p style="margin:0;font-family:{!! $font !!}">© {{ now()->year }} <a href="{{ $site }}" style="color:#8a93ad;text-decoration:none;font-family:{!! $font !!}">{{ $name }}</a></p>
    </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
