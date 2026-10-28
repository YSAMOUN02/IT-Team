@extends('admin.layout')

@php
    $live = collect(array_keys($placements))->filter(fn ($key) => \App\Support\Ads::isLive($key));
@endphp

@section('title', 'Ads')
@section('subtitle', 'Put your own banner image or a Google AdSense ad in each spot. A spot that is off shows nothing; a spot that is on with nothing to show yet shows an “Ad space” placeholder.')
@section('crumbs')<span aria-hidden="true">/</span><span>System</span><span aria-hidden="true">/</span><span>Ads</span>@endsection

@section('content')
    <form method="post" enctype="multipart/form-data" data-main-form action="{{ route('admin.ads.update') }}">
        @csrf @method('PUT')

        <div class="form-grid reveal">
            <div>
                @foreach ($placements as $key => $placement)
                    @php
                        $enabled = (bool) old("ad_{$key}_enabled", $values["ad_{$key}_enabled"] ?? false);
                        $adType = old("ad_{$key}_type", \App\Support\Ads::type($key));
                        $isLive = $live->contains($key);
                        $hasUnit = filled($values["ad_{$key}_slot"] ?? null) || filled($values["ad_{$key}_code"] ?? null);
                        $waiting = $adType === 'image' ? 'Upload an image to show your banner.' : ($hasUnit ? 'Add the publisher ID below to show the real ad.' : 'Add an ad unit to show the real ad.');
                    @endphp
                    <section class="card ad-card" id="ad-{{ $key }}">
                        <div class="card-head">
                            <div class="ad-card-title">
                                @include('admin.partials.ad-sketch', ['where' => $placement['where']])
                                <div>
                                    <h2>{{ $placement['label'] }}</h2>
                                    <p>{{ $placement['description'] }}</p>
                                </div>
                            </div>
                            @if ($isLive)
                                <span class="status status-on">Live · {{ $adType === 'image' ? 'your image' : 'Google' }}</span>
                            @elseif ($enabled)
                                <span class="status status-sched" title="{{ $waiting }}">Placeholder shown</span>
                            @else
                                <span class="status status-off">Off · not shown</span>
                            @endif
                        </div>
                        <div class="card-body fields">
                            <label class="switch">
                                <span class="switch-text"><strong>Show an ad here</strong><span>Off: nothing on the page. On with nothing to show yet: an “Ad space” placeholder.</span></span>
                                <input type="checkbox" name="ad_{{ $key }}_enabled" value="1" @checked($enabled)>
                                <span class="switch-ui" aria-hidden="true"></span>
                            </label>

                            <div class="field">
                                <span class="field-label"><span>What to show</span></span>
                                <div class="segmented ad-type" role="radiogroup" aria-label="What to show in {{ $placement['label'] }}">
                                    @foreach (\App\Support\Ads::TYPES as $value => $label)
                                        <label class="seg"><input type="radio" name="ad_{{ $key }}_type" value="{{ $value }}" @checked($adType === $value)><span>{{ $label }}</span></label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="ad-own">
                                <div class="cols-2">
                                    @include('admin.fields.image', ['name' => "ad_{$key}_image", 'remove' => "remove_ad_{$key}_image", 'label' => 'Banner image', 'value' => $values["ad_{$key}_image"] ?? null, 'formats' => 'JPG, PNG, WebP or GIF', 'help' => "Best at {$placement['size']}. Animated GIFs work too."])
                                    @include('admin.fields.image', ['name' => "ad_{$key}_image_phone", 'remove' => "remove_ad_{$key}_image_phone", 'label' => 'Phone image (optional)', 'value' => $values["ad_{$key}_image_phone"] ?? null, 'formats' => 'JPG, PNG, WebP or GIF', 'help' => 'Shown on phones instead, about 600 × 300 px. Leave empty and phones get the banner.'])
                                </div>
                                <div class="cols-2">
                                    @include('admin.fields.input', ['name' => "ad_{$key}_link", 'label' => 'Link when clicked', 'value' => $values["ad_{$key}_link"] ?? '', 'placeholder' => '/courses/pivot-table', 'help' => 'A page of this site, or a full address like https://t.me/… Other websites open in a new tab. Empty: no link.'])
                                    @include('admin.fields.input', ['name' => "ad_{$key}_alt", 'label' => 'Image description', 'value' => $values["ad_{$key}_alt"] ?? '', 'placeholder' => 'Pivot Table course, only $19', 'help' => 'Read out by screen readers, and shown if the picture cannot load.'])
                                </div>
                                @if ($clicks[$key] ?? 0)
                                    <p class="ad-clicks">@include('admin.partials.icon', ['name' => 'chart']) <b>{{ number_format($clicks[$key]) }}</b> {{ \Illuminate\Support\Str::plural('click', $clicks[$key]) }} in the last 30 days</p>
                                @endif
                            </div>

                            <div class="ad-google">
                                <div class="cols-2">
                                    @include('admin.fields.input', ['name' => "ad_{$key}_slot", 'label' => 'Ad unit (slot) ID', 'value' => $values["ad_{$key}_slot"] ?? '', 'placeholder' => '1234567890', 'help' => 'AdSense → Ads → By ad unit → the data-ad-slot number.'])
                                    <div class="field">
                                        <label class="field-label" for="f-ad_{{ $key }}_format"><span>Format</span></label>
                                        <select id="f-ad_{{ $key }}_format" name="ad_{{ $key }}_format">
                                            @foreach (\App\Support\Ads::FORMATS as $value => $label)
                                                <option value="{{ $value }}" @selected(old("ad_{$key}_format", $values["ad_{$key}_format"] ?? $placement['format']) === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <details class="ad-code" @if (filled($values["ad_{$key}_code"] ?? null)) open @endif>
                                    <summary>Use custom ad code instead</summary>
                                    @include('admin.fields.textarea', ['name' => "ad_{$key}_code", 'label' => 'Ad code', 'value' => $values["ad_{$key}_code"] ?? '', 'rows' => 5, 'help' => 'Paste the full code from AdSense (or another network). When filled it replaces the slot ID above.'])
                                </details>
                            </div>
                        </div>
                    </section>
                @endforeach

                <section class="card">
                    <div class="card-head"><div><h2>Google AdSense account</h2><p>Only needed for spots set to Google AdSense. From AdSense → Account → Account information.</p></div></div>
                    <div class="card-body fields">
                        @include('admin.fields.input', ['name' => 'ads_publisher_id', 'label' => 'Publisher ID', 'value' => $values['ads_publisher_id'] ?? '', 'placeholder' => 'ca-pub-1234567890123456', 'help' => 'Also adds the AdSense site-verification tag and /ads.txt.'])
                        <label class="switch">
                            <span class="switch-text">
                                <strong>Auto ads</strong>
                                <span>Let Google place extra ads automatically (turn on in AdSense too). Leave off to use only the spots above.</span>
                            </span>
                            <input type="checkbox" name="ads_auto" value="1" @checked(old('ads_auto', $values['ads_auto'] ?? false))>
                            <span class="switch-ui" aria-hidden="true"></span>
                        </label>
                        @include('admin.fields.textarea', ['name' => 'ads_txt_extra', 'label' => 'ads.txt extra lines', 'value' => $values['ads_txt_extra'] ?? '', 'rows' => 3, 'help' => 'Optional, for other ad networks. Added under the Google line.'])
                    </div>
                </section>
            </div>

            <div class="form-side">
                <section class="card">
                    <div class="card-head"><h2>Status</h2></div>
                    <div class="card-body">
                        <dl class="meta-list">
                            <dt>Live spots</dt><dd>{{ $live->count() }} of {{ count($placements) }}</dd>
                            <dt>Banner clicks</dt><dd>{{ number_format(array_sum($clicks)) }} <span class="muted">· 30 days</span></dd>
                            <dt>AdSense</dt><dd>{{ $publisher ?? 'Not set' }}</dd>
                            <dt>Auto ads</dt><dd>{{ \App\Support\Ads::autoAds() ? 'On' : 'Off' }}</dd>
                            <dt>ads.txt</dt><dd>@if ($publisher)<a href="{{ url('ads.txt') }}" target="_blank" rel="noopener">/ads.txt ↗</a>@else Needs publisher ID @endif</dd>
                        </dl>
                    </div>
                    <div class="card-foot save-bar">
                        <button type="submit" class="btn btn-primary btn-block">@include('admin.partials.icon', ['name' => 'check']) Save ad settings</button>
                    </div>
                </section>
                <section class="card">
                    <div class="card-head"><h2>Good to know</h2></div>
                    <div class="card-body" style="font-size:13px;color:var(--text-2);display:grid;gap:8px">
                        <p style="margin:0"><b>Your own image:</b> upload a banner, choose where a click goes, save. It shows straight away; no Google approval needed.</p>
                        <p style="margin:0">Clicks on your banners are counted (not admins or search-engine bots).</p>
                        <p style="margin:0">Ads never show in the admin, at checkout or in the student course player.</p>
                        <p style="margin:0">A spot that is on with nothing to show yet shows an “Ad space” placeholder linking to your contact page.</p>
                        <p style="margin:0"><b>Google AdSense:</b> new sites must be approved by Google first. If Google has no ad to show, the spot hides itself.</p>
                    </div>
                </section>
            </div>
        </div>
    </form>
@endsection
