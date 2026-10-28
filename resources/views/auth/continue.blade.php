{{--
    Shown when a visitor who is not signed in clicks Buy / Enrol: what they chose
    on one side, "Create account" / "Sign in" (and Google) on the other. After
    signing in they land back on the checkout with the same plan.
--}}
@extends('layouts.site')

@section('title', __('account.continue_title').' · '.$course->title)
@section('no_ads', true)

@section('content')
    <section class="section auth-page gate-page">
        <div class="container">
            <ol class="gate-steps" aria-label="{{ __('pricing.steps_title') }}">
                <li class="is-current"><span>1</span> {{ __('account.step_account') }}</li>
                @unless ($course->isFree())
                    <li><span>2</span> {{ __('account.step_pay') }}</li>
                @endunless
                <li><span>{{ $course->isFree() ? 2 : 3 }}</span> {{ __('account.step_learn') }}</li>
            </ol>

            <div class="gate">
                <aside class="card gate-summary">
                    <p class="eyebrow">{{ __('account.your_choice') }}</p>
                    @if ($course->image)
                        <img class="gate-cover" src="{{ $course->mediaUrl() }}" alt="">
                    @endif
                    <h2>{{ $course->title }}</h2>
                    <p class="gate-meta">
                        @if ($course->duration)<span>{{ __('account.duration') }}: {{ $course->duration }}</span>@endif
                        @if ($course->lessons_count)<span>{{ __('pricing.lessons', ['count' => $course->lessons_count]) }}</span>@endif
                        @if ($course->certificate_enabled)<span>{{ __('pricing.certificate') }}</span>@endif
                    </p>

                    <div class="gate-price">
                        @if ($course->isFree())
                            <span>{{ __('account.access_lifetime') }}</span>
                            <strong>{{ __('account.free') }}</strong>
                        @elseif ($plan)
                            <span>
                                {{ $plan->periodLabel() }}
                                @if ($plan->discountActive())<b class="disc">−{{ $plan->discount_percent }}%</b>@endif
                            </span>
                            <strong>
                                @if ($plan->discountActive())<s>{{ $plan->formattedOriginalPrice() }}</s>@endif
                                {{ $plan->formattedPrice() }}
                            </strong>
                        @endif
                    </div>
                    @if (! $course->isFree() && $course->plans->count() > 1)
                        <a class="gate-change" href="{{ $course->url() }}#buy">{{ __('account.change_plan') }}</a>
                    @endif

                    <ul class="gate-includes">
                        @foreach (array_slice(__('pricing.includes'), 0, 4) as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>

                    @unless ($course->isFree())
                        <p class="gate-secure">{{ __('account.secure_payment') }}</p>
                        <p class="pay-badges" aria-label="{{ __('account.payment_method') }}">
                            <span>ABA KHQR</span><span>VISA</span><span>Mastercard</span><span>UnionPay</span>
                        </p>
                    @endunless
                </aside>

                <div class="card auth-box gate-form" data-tabs>
                    <h1>{{ __('account.continue_title') }}</h1>
                    <p class="lead">{{ $course->isFree() ? __('account.continue_text_free') : __('account.continue_text') }}</p>
                    @if (session('status'))
                        <div class="alert alert-success" role="status">{{ session('status') }}</div>
                    @endif

                    @include('auth._google')

                    <div class="auth-tabs" role="tablist">
                        <a href="{{ request()->fullUrlWithQuery(['tab' => 'register']) }}" role="tab" data-tab="register" aria-selected="{{ $tab === 'register' ? 'true' : 'false' }}">{{ __('account.create_account') }}</a>
                        <a href="{{ request()->fullUrlWithQuery(['tab' => 'login']) }}" role="tab" data-tab="login" aria-selected="{{ $tab === 'login' ? 'true' : 'false' }}">{{ __('account.sign_in') }}</a>
                    </div>
                    <div role="tabpanel" data-panel="register" @if ($tab !== 'register') hidden @endif>
                        @include('auth._register-form')
                    </div>
                    <div role="tabpanel" data-panel="login" @if ($tab !== 'login') hidden @endif>
                        @include('auth._login-form')
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        // Switch between "Create account" and "Sign in" without reloading (links still work without JS).
        document.querySelectorAll('[data-tabs]').forEach((box) => {
            box.querySelectorAll('[data-tab]').forEach((tab) => tab.addEventListener('click', (e) => {
                e.preventDefault();
                box.querySelectorAll('[data-tab]').forEach((t) => t.setAttribute('aria-selected', String(t === tab)));
                box.querySelectorAll('[data-panel]').forEach((p) => { p.hidden = p.dataset.panel !== tab.dataset.tab; });
                box.querySelector('[data-panel="' + tab.dataset.tab + '"] input:not([type=hidden])')?.focus();
            }));
        });
    </script>
@endpush
