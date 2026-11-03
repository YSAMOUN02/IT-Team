{{--
    One invoice, on its own page so it prints as a clean A4 sheet. "Download PDF" opens the
    browser's print window (Save as PDF): browsers set Khmer correctly, PHP PDF libraries do not.
--}}
@php
    $site = setting('site_name', config('app.name'));
    $logo = setting('logo') ?: setting('favicon');
    $refunded = $invoice->status === 'refunded';
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ $invoice->number }} · {{ $site }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;500;600;700&display=swap">
<style>
    :root { --accent: #343090; --accent-2: #5f59f7; --ink: #111827; --ink-2: #5c6b8d; --line: #e4e6f2; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #eef0fb; color: var(--ink); font: 14px/1.6 "Kantumruy Pro", "Noto Sans Khmer", "Khmer UI", system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
    .bar { max-width: 820px; margin: 24px auto 0; padding: 0 16px; display: flex; gap: 10px; align-items: center; justify-content: space-between; flex-wrap: wrap; }
    .bar a { color: var(--accent-2); text-decoration: none; font-weight: 600; }
    .bar .hint { color: var(--ink-2); font-size: 12.5px; }
    .btn { display: inline-flex; align-items: center; gap: 8px; border: 0; cursor: pointer; padding: 11px 22px; border-radius: 999px; font: 700 14px/1 inherit; font-family: inherit; color: #fff; background: linear-gradient(135deg, var(--accent-2), var(--accent)); box-shadow: 0 8px 22px rgba(95, 89, 247, .3); }
    .sheet { position: relative; max-width: 820px; margin: 16px auto 40px; background: #fff; border-radius: 20px; overflow: hidden; box-shadow: 0 14px 44px rgba(52, 48, 144, .12); }
    .head { display: flex; justify-content: space-between; gap: 24px; align-items: flex-start; padding: 36px 44px 30px; color: #fff; background: linear-gradient(135deg, var(--accent), var(--accent-2)); }
    .brand { display: flex; gap: 12px; align-items: center; font-size: 20px; font-weight: 700; }
    .brand img { width: 46px; height: 46px; border-radius: 50%; background: #fff; }
    .brand small { display: block; font-size: 12.5px; font-weight: 400; color: #dcdaff; }
    .title { text-align: right; }
    .title h1 { margin: 0; font-size: 30px; letter-spacing: .04em; text-transform: uppercase; }
    .title p { margin: 4px 0 0; color: #dcdaff; }
    .body { padding: 34px 44px 40px; }
    .meta { display: grid; grid-template-columns: 1.3fr 1fr; gap: 24px; margin-bottom: 30px; }
    .label { margin: 0 0 6px; font-size: 11.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--accent-2); }
    .meta p { margin: 0; }
    .meta .who { font-size: 16px; font-weight: 700; }
    .kv { display: grid; grid-template-columns: auto 1fr; gap: 4px 16px; }
    .kv dt { color: var(--ink-2); }
    .kv dd { margin: 0; text-align: right; font-weight: 600; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; font-size: 11.5px; letter-spacing: .08em; text-transform: uppercase; color: var(--ink-2); padding: 12px 14px; background: #f5f5ff; }
    th:last-child, td:last-child { text-align: right; }
    td { padding: 18px 14px; border-bottom: 1px solid var(--line); vertical-align: top; }
    td .course { font-size: 15.5px; font-weight: 700; }
    td .sub { color: var(--ink-2); font-size: 13px; }
    .total { display: flex; justify-content: flex-end; margin-top: 18px; }
    .total div { min-width: 260px; padding: 16px 20px; border-radius: 14px; background: #f5f5ff; display: flex; justify-content: space-between; align-items: baseline; gap: 20px; }
    .total b { font-size: 24px; color: var(--accent); }
    .pill { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 12.5px; font-weight: 700; background: #e7f8ef; color: #127a45; }
    .pill.refunded { background: #fdecec; color: #b42318; }
    .foot { margin-top: 34px; padding-top: 20px; border-top: 1px dashed var(--line); display: flex; justify-content: space-between; gap: 20px; flex-wrap: wrap; color: var(--ink-2); font-size: 13px; }
    .stamp { position: absolute; top: 210px; right: 40px; transform: rotate(-12deg); border: 3px solid #b42318; color: #b42318; padding: 6px 18px; border-radius: 10px; font-size: 22px; font-weight: 800; letter-spacing: .1em; opacity: .75; }
    @media (max-width: 640px) {
        .head { flex-direction: column; padding: 28px 24px; }
        .title { text-align: left; }
        .body { padding: 26px 22px 30px; }
        .meta { grid-template-columns: 1fr; }
    }
    @page { size: A4; margin: 12mm; }
    @media print {
        body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .bar { display: none; }
        .sheet { margin: 0; box-shadow: none; border-radius: 0; max-width: none; }
    }
</style>
</head>
<body>
<div class="bar">
    <a href="{{ $back }}">&larr; {{ __('account.invoice_back') }}</a>
    <span>
        <span class="hint">{{ __('account.invoice_print_hint') }}</span>&nbsp;
        <button class="btn" type="button" onclick="window.print()">⬇ {{ __('account.invoice_download') }}</button>
    </span>
</div>

<main class="sheet">
    <header class="head">
        <div class="brand">
            @if ($logo)<img src="{{ media_url($logo) }}" alt="">@endif
            <div>{{ $site }}<small>{{ preg_replace('#^https?://#', '', rtrim((string) config('app.public_url'), '/')) }}</small></div>
        </div>
        <div class="title">
            <h1>{{ __('account.invoice') }}</h1>
            <p>{{ $invoice->number }}</p>
        </div>
    </header>

    @if ($refunded)<div class="stamp">{{ __('account.invoice_status_refunded') }}</div>@endif

    <div class="body">
        <div class="meta">
            <div>
                <p class="label">{{ __('account.invoice_billed_to') }}</p>
                <p class="who">{{ $invoice->student_name }}</p>
                <p>{{ $invoice->student_email }}</p>
                @if ($invoice->student_phone)<p>{{ __('account.invoice_phone') }}: {{ $invoice->student_phone }}</p>@endif
            </div>
            <dl class="kv">
                <dt>{{ __('account.invoice_number') }}</dt><dd>{{ $invoice->number }}</dd>
                <dt>{{ __('account.invoice_date') }}</dt><dd>{{ $invoice->issued_at->format('d/m/Y') }}</dd>
                <dt>{{ __('account.invoice_paid_by') }}</dt><dd>{{ $invoice->methodLabel() }}</dd>
                @if ($invoice->reference)<dt>{{ __('account.invoice_reference') }}</dt><dd>{{ $invoice->reference }}</dd>@endif
                <dt>{{ __('account.status') }}</dt><dd><span @class(['pill', 'refunded' => $refunded])>{{ __('account.invoice_status_'.$invoice->status) }}</span></dd>
            </dl>
        </div>

        <table>
            <thead><tr><th>{{ __('account.invoice_item') }}</th><th>{{ __('account.invoice_access') }}</th><th>{{ __('account.amount') }}</th></tr></thead>
            <tbody>
                <tr>
                    <td>
                        <div class="course">{{ $invoice->course_title }}</div>
                        <div class="sub">{{ __('account.course') }} · {{ $site }}</div>
                    </td>
                    <td>
                        {{ $invoice->access_days ? __('account.invoice_days', ['days' => $invoice->access_days]) : __('account.invoice_lifetime') }}
                        @if ($invoice->access_until)<div class="sub">{{ __('account.invoice_until', ['date' => $invoice->access_until->format('d/m/Y')]) }}</div>@endif
                    </td>
                    <td><b>{{ $invoice->formattedAmount() }}</b></td>
                </tr>
            </tbody>
        </table>

        <div class="total"><div><span>{{ __('account.invoice_total') }}</span><b>{{ $invoice->formattedAmount() }}</b></div></div>

        @if ($invoice->note)
            <p style="margin:22px 0 0"><span class="label">{{ __('account.invoice_note') }}</span><br>{{ $invoice->note }}</p>
        @endif

        <div class="foot">
            <span>{{ __('account.invoice_thanks') }}</span>
            <span>{{ setting('phone') }}@if (setting('phone') && setting('email')) · @endif{{ setting('email') }}</span>
        </div>
    </div>
</main>
</body>
</html>
