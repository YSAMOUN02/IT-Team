@extends('layouts.site')

@section('title', __('account.khqr_title').' · '.$order->number)
@section('no_ads', true)

@php
    $merchant = setting('khqr_merchant_name') ?: setting('site_name', config('app.name'));
    $khr = strtoupper($order->currency) === 'KHR';
@endphp

@section('content')
<section class="section khqr-page">
    <div class="container khqr-wrap">
        <div class="khqr-grid">
            {{-- The card, styled like the KHQR cards banks print --}}
            <div class="khqr-card" data-khqr data-status-url="{{ route('checkout.khqr.status', $order) }}" data-expires="{{ $khqr['expires_at'] }}">
                <div class="khqr-head"><span class="khqr-logo">KHQR</span></div>
                <div class="khqr-info">
                    <p class="khqr-merchant">{{ $merchant }}</p>
                    <p class="khqr-amount"><b>{{ $khr ? number_format((float) $order->amount) : number_format((float) $order->amount, 2) }}</b> <span>{{ $khr ? 'KHR' : 'USD' }}</span></p>
                </div>
                <div class="khqr-cut" aria-hidden="true"></div>
                <div class="khqr-code">
                    <div class="khqr-qr" data-qr="{{ $khqr['qr'] }}" role="img" aria-label="KHQR {{ $order->formattedAmount() }}"></div>
                    <div class="khqr-expired" data-expired hidden>
                        <p>{{ __('account.khqr_expired') }}</p>
                        <a class="btn btn-primary btn-sm" href="{{ route('checkout.khqr', $order) }}">{{ __('account.khqr_new') }}</a>
                    </div>
                </div>
                <p class="khqr-timer">{{ __('account.khqr_expires') }} <b data-timer>--:--</b></p>
            </div>

            <div class="khqr-side">
                <p class="eyebrow">{{ __('account.khqr_order') }} <span class="mono">{{ $order->number }}</span></p>
                <h1>{{ __('account.khqr_title') }}</h1>
                <p class="lead">{{ __('account.khqr_scan') }}</p>

                <ol class="khqr-steps">
                    <li>{{ __('account.khqr_steps_1') }}</li>
                    <li>{{ __('account.khqr_steps_2') }}</li>
                    <li>{{ __('account.khqr_steps_3') }}</li>
                </ol>

                <dl class="summary-lines">
                    <dt>{{ __('account.course') }}</dt><dd>{{ $order->course_title }}</dd>
                    @if ($order->plan_label)<dt>{{ __('account.plan') }}</dt><dd>{{ $order->plan_label }}</dd>@endif
                    <dt>{{ __('account.khqr_pay_to') }}</dt><dd>{{ $merchant }}</dd>
                    <dt>{{ __('account.amount') }}</dt><dd><b>{{ $order->formattedAmount() }}</b></dd>
                </dl>

                @if ($autoCheck)
                    <div class="khqr-wait"><span class="spinner" aria-hidden="true"></span><div><b>{{ __('account.khqr_waiting') }}</b><small>{{ __('account.khqr_auto') }}</small></div></div>
                @elseif ($order->status === 'pending')
                    <p class="muted">{{ __('account.khqr_manual') }}</p>
                    <form method="post" action="{{ route('checkout.khqr.paid', $order) }}">
                        @csrf
                        <button class="btn btn-primary btn-lg">✓ {{ __('account.khqr_i_paid') }}</button>
                    </form>
                @else
                    <div class="alert alert-info">{{ __('account.khqr_review_text') }}</div>
                @endif

                <p class="khqr-save">
                    <button type="button" class="btn btn-outline btn-sm" data-qr-save>⬇ {{ __('account.khqr_save') }}</button>
                    <small class="muted">{{ __('account.khqr_save_hint') }}</small>
                </p>

                @if ($simulate)
                    <form method="post" action="{{ route('checkout.khqr.test', $order) }}" class="khqr-test">
                        @csrf
                        <button class="btn btn-outline btn-sm">{{ __('account.khqr_test') }}</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
    {{-- QR drawing (MIT, kazuhikoarase/qrcode-generator), pinned. --}}
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
    <script>
    (() => {
        const card = document.querySelector('[data-khqr]');
        const box = card.querySelector('[data-qr]');
        const text = box.dataset.qr;
        let canvas = null;

        // Draw: high error correction (Q), crisp squares, a white quiet zone; the KHQR "៛/$" badge sits in the middle.
        if (window.qrcode) {
            const qr = qrcode(0, 'Q'); // Q: room for the badge covering the middle
            qr.addData(text);
            qr.make();
            const count = qr.getModuleCount(), cell = 8, margin = 2 * cell, size = count * cell + margin * 2;
            canvas = document.createElement('canvas');
            canvas.width = canvas.height = size;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, size, size);
            ctx.fillStyle = '#000';
            for (let r = 0; r < count; r++) for (let c = 0; c < count; c++) if (qr.isDark(r, c)) ctx.fillRect(margin + c * cell, margin + r * cell, cell, cell);
            const badge = size * 0.16, x = (size - badge) / 2;
            ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.arc(size / 2, size / 2, badge * 0.62, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = '#000'; ctx.beginPath(); ctx.arc(size / 2, size / 2, badge / 2, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = '#fff'; ctx.font = 'bold ' + Math.round(badge * 0.6) + 'px Arial'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            ctx.fillText({{ Js::from($khr ? '៛' : '$') }}, size / 2, size / 2 + 1);
            box.appendChild(canvas);
        }

        // Save the QR as a picture, to open in a bank app on this same phone.
        document.querySelector('[data-qr-save]')?.addEventListener('click', () => {
            if (!canvas) return;
            const a = document.createElement('a');
            a.href = canvas.toDataURL('image/png');
            a.download = 'KHQR-{{ $order->number }}.png';
            a.click();
        });

        // Countdown; when it runs out the QR is covered (banks refuse expired codes).
        const timer = card.querySelector('[data-timer]');
        const expiredBox = card.querySelector('[data-expired]');
        const ends = new Date(card.dataset.expires).getTime();
        const tick = () => {
            const left = Math.max(0, Math.round((ends - Date.now()) / 1000));
            timer.textContent = String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0');
            if (left === 0) { expiredBox.hidden = false; box.classList.add('is-expired'); return false; }
            return true;
        };
        tick();
        const clock = setInterval(() => { if (!tick()) clearInterval(clock); }, 1000);

        // Ask the site every few seconds whether the payment arrived.
        const poll = async () => {
            try {
                const res = await fetch(card.dataset.statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (res.ok) {
                    const data = await res.json();
                    if (data.redirect) { location.href = data.redirect; return; }
                }
            } catch (e) { /* offline for a moment: try again */ }
            setTimeout(poll, 4000);
        };
        @if ($autoCheck) setTimeout(poll, 4000); @endif
    })();
    </script>
@endpush
