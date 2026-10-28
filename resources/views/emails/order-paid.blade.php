<!doctype html>
<html lang="{{ app()->getLocale() }}">
<body style="margin:0;padding:24px;background:#f0f2fb;font-family:Arial,'Kantumruy Pro',sans-serif;color:#111827">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden">
    <tr><td style="background:#343090;color:#fff;padding:22px 28px;font-size:20px;font-weight:bold">{{ setting('site_name', config('app.name')) }}</td></tr>
    <tr><td style="padding:28px">
        <p style="margin:0 0 12px;font-size:16px">{{ __('account.mail_hello', ['name' => $order->buyer_name]) }}</p>
        <p style="margin:0 0 20px;line-height:1.6">{{ __('account.mail_paid_text', ['course' => $order->course_title]) }}</p>
        <table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="border:1px solid #e4e6ef;border-radius:10px;font-size:14px">
            <tr><td style="color:#5c6b8d">{{ __('account.order_number') }}</td><td align="right"><b>{{ $order->number }}</b></td></tr>
            <tr><td style="color:#5c6b8d">{{ __('account.course') }}</td><td align="right">{{ $order->course_title }}</td></tr>
            <tr><td style="color:#5c6b8d">{{ __('account.amount') }}</td><td align="right"><b>{{ $order->formattedAmount() }}</b></td></tr>
            <tr><td style="color:#5c6b8d">{{ __('account.paid_on') }}</td><td align="right">{{ $order->paid_at?->format('d/m/Y H:i') }}</td></tr>
        </table>
        <p style="margin:24px 0 0"><a href="{{ route('account.courses') }}" style="display:inline-block;background:#343090;color:#fff;text-decoration:none;padding:12px 22px;border-radius:999px;font-weight:bold">{{ __('account.start_learning') }}</a></p>
    </td></tr>
</table>
</body>
</html>
