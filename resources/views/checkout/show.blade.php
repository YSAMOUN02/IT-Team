@extends('layouts.site')

@section('title', __('account.checkout_title'))
@section('no_ads', true)

@section('content')
    <section class="section checkout">
        <div class="container">
            <p class="eyebrow"><a href="{{ $course->url() }}">{{ $course->title }}</a></p>
            <h1>{{ __('account.checkout_title') }}</h1>

            @if (! $available)
                <div class="alert alert-error">{{ __('account.payments_unavailable') }}</div>
            @else
                <form method="post" action="{{ route('checkout.store', $course->slug) }}" class="checkout-grid">
                    @csrf
                    <div class="checkout-main">
                        <div class="card checkout-card">
                            <h2>{{ __('account.choose_plan') }}</h2>
                            @error('plan_id')<small class="field-error">{{ $message }}</small>@enderror
                            <div class="plan-picker" data-plans>
                                @foreach ($plans as $plan)
                                    <label class="plan-option">
                                        <input type="radio" name="plan_id" value="{{ $plan->id }}" required
                                               @checked((int) old('plan_id', $selected?->id) === $plan->id)
                                               data-label="{{ $plan->periodLabel() }}" data-price="{{ $plan->formattedPrice() }}"
                                               data-original="{{ $plan->discountActive() ? $plan->formattedOriginalPrice() : '' }}"
                                               data-discount="{{ $plan->discountActive() ? $plan->discount_percent : 0 }}">
                                        <span class="plan-box">
                                            <span class="plan-period">
                                                {{ $plan->periodLabel() }}
                                                @if ($plan->discountActive())<b class="disc">−{{ $plan->discount_percent }}%</b>@endif
                                            </span>
                                            <span class="plan-price">
                                                @if ($plan->discountActive())<s>{{ $plan->formattedOriginalPrice() }}</s>@endif
                                                <strong>{{ $plan->formattedPrice() }}</strong>
                                            </span>
                                            @if ($plan->discountActive() && $plan->discount_ends_at)
                                                <small class="plan-until">{{ __('account.discount_until', ['date' => $plan->discount_ends_at->format('d/m/Y')]) }}</small>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="card checkout-card">
                            <h2>{{ __('account.your_details') }}</h2>
                            <div class="form-grid">
                                <label class="field">
                                    <span>{{ __('account.name') }} *</span>
                                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="120" autocomplete="name">
                                    @error('name')<small class="field-error">{{ $message }}</small>@enderror
                                </label>
                                <label class="field">
                                    <span>{{ __('account.email') }} *</span>
                                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="190" autocomplete="email">
                                    @error('email')<small class="field-error">{{ $message }}</small>@enderror
                                </label>
                                <label class="field field-full">
                                    <span>{{ __('account.phone') }}</span>
                                    <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="40" autocomplete="tel">
                                </label>
                            </div>
                        </div>

                        <div class="card checkout-card">
                            <h2>{{ __('account.payment_method') }}</h2>
                            <div class="pay-options">
                                @foreach ([
                                    'cards' => [__('account.pay_card'), __('account.pay_card_text'), '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 10h19M6 15h4"/>'],
                                    'abapay_khqr' => [__('account.pay_khqr'), __('account.pay_khqr_text'), '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v.01M14 20h.01M17 20h4v-3"/>'],
                                    '' => [__('account.pay_any'), __('account.pay_any_text'), '<circle cx="12" cy="12" r="9"/><path d="M8 12h8M12 8v8"/>'],
                                ] as $value => [$label, $text, $icon])
                                    <label class="pay-option">
                                        <input type="radio" name="payment_option" value="{{ $value }}" @checked(old('payment_option', 'cards') === $value)>
                                        <span class="pay-option-box">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">{!! $icon !!}</svg>
                                            <span><strong>{{ $label }}</strong><small>{{ $text }}</small></span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="secure-note">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                                {{ __('account.secure_note') }}
                            </p>
                            @if ($testGateway)
                                <div class="alert alert-info">{{ __('account.test_mode_note') }}</div>
                            @endif
                        </div>
                    </div>

                    <aside class="card checkout-summary">
                        <h2>{{ __('account.order_summary') }}</h2>
                        <div class="summary-course">
                            @if ($course->image)
                                <img src="{{ $course->mediaUrl() }}" alt="">
                            @endif
                            <div>
                                <strong>{{ $course->title }}</strong>
                                <small data-sum-label>{{ $selected?->periodLabel() }}</small>
                            </div>
                        </div>
                        @if ($enrollment?->expires_at && $enrollment->isActive())
                            <p class="access-note">{{ __('account.enrolled_until', ['date' => $enrollment->expires_at->format('d/m/Y')]) }}</p>
                        @endif
                        <dl class="summary-lines">
                            <dt>{{ __('account.course') }}</dt>
                            <dd data-sum-original>{{ $selected?->discountActive() ? $selected->formattedOriginalPrice() : $selected?->formattedPrice() }}</dd>
                            <dt class="sum-discount" data-sum-discount-row @unless ($selected?->discountActive()) hidden @endunless>{{ __('account.discount') }}</dt>
                            <dd class="sum-discount" data-sum-discount @unless ($selected?->discountActive()) hidden @endunless>−{{ $selected?->discount_percent }}%</dd>
                            <dt class="total">{{ __('account.amount') }}</dt><dd class="total" data-sum-total>{{ $selected?->formattedPrice() }}</dd>
                        </dl>
                        <label class="check terms">
                            <input type="checkbox" name="terms" value="1" required @checked(old('terms'))>
                            <span>{{ __('account.agree_terms') }}</span>
                        </label>
                        @error('terms')<small class="field-error">{{ $message }}</small>@enderror
                        <button type="submit" class="btn btn-primary btn-block btn-lg">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                            <span data-pay-label data-template="{{ __('account.pay', ['amount' => '__AMOUNT__']) }}">{{ __('account.pay', ['amount' => $selected?->formattedPrice()]) }}</span>
                        </button>
                        <p class="pay-badges"><span>VISA</span><span>Mastercard</span><span>UnionPay</span><span>ABA KHQR</span></p>
                    </aside>
                </form>
            @endif
        </div>
    </section>
@endsection

@push('scripts')
<script>
// Keep the order summary in step with the chosen plan (the server recalculates the price anyway).
document.querySelectorAll('[data-plans] input[name=plan_id]').forEach((radio) => radio.addEventListener('change', () => {
    const d = radio.dataset;
    const discounted = Number(d.discount) > 0;
    document.querySelector('[data-sum-label]').textContent = d.label;
    document.querySelector('[data-sum-original]').textContent = discounted ? d.original : d.price;
    document.querySelector('[data-sum-discount-row]').hidden = !discounted;
    const disc = document.querySelector('[data-sum-discount]');
    disc.hidden = !discounted;
    disc.textContent = '−' + d.discount + '%';
    document.querySelector('[data-sum-total]').textContent = d.price;
    const pay = document.querySelector('[data-pay-label]');
    pay.textContent = pay.dataset.template.replace('__AMOUNT__', d.price);
}));
</script>
@endpush
