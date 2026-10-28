{{-- "Price & access": free switch, currency and the price plans (period + price + discount). --}}
@php
    $free = (bool) old('is_free', $course->is_free);
    $rows = old('plans') ?? $course->plans->map(fn ($p) => [
        'id' => $p->id, 'label' => $p->label, 'days' => $p->days, 'price' => $p->price,
        'discount_percent' => $p->discount_percent ?: '',
        'discount_starts_at' => $p->discount_starts_at?->toDateString(), 'discount_ends_at' => $p->discount_ends_at?->toDateString(),
    ])->all();
    if (! $rows) {
        $rows = [['id' => null, 'label' => '', 'days' => 365, 'price' => '', 'discount_percent' => '', 'discount_starts_at' => '', 'discount_ends_at' => '']];
    }
    $presets = [30 => '1 month', 90 => '3 months', 180 => '6 months', 365 => '1 year', '' => 'Lifetime'];
@endphp
<section class="card" id="pricing">
    <div class="card-head">
        <div><h2>Price &amp; access</h2><p>Sell the course in one or more periods, each with its own price and discount. Card / ABA KHQR through ABA PayWay.</p></div>
    </div>
    <div class="card-body fields">
        <label class="switch free-switch">
            <span class="switch-text">
                <strong>Free course</strong>
                <span>Students only create an account and press “Enrol free”. No payment, no QR code. Lifetime access.</span>
            </span>
            <input type="checkbox" name="is_free" value="1" @checked($free) data-free-toggle>
            <span class="switch-ui" aria-hidden="true"></span>
        </label>

        <div data-paid-only @if ($free) hidden @endif>
            <div class="cols-3" style="margin-bottom:14px">
                <div class="field">
                    <label class="field-label" for="f-currency"><span>Currency</span></label>
                    <select id="f-currency" name="currency" data-currency>
                        @foreach (['USD' => 'US dollar ($)', 'KHR' => 'Riel (៛)'] as $code => $label)
                            <option value="{{ $code }}" @selected(old('currency', $course->currency ?: 'USD') === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @error('plans.*')<span class="error-text">{{ $message }}</span>@enderror
            <div class="plan-rows" data-plan-rows>
                @foreach ($rows as $i => $row)
                    @php
                        $days = $row['days'] ?? '';
                        $preset = in_array((string) $days, array_map('strval', array_keys($presets)), true) ? (string) $days : 'custom';
                    @endphp
                    <div class="plan-row" data-plan-row>
                        <input type="hidden" name="plans[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}" data-plan-id>
                        <div class="plan-grid">
                            <div class="field">
                                <span class="field-label"><span>Access period</span></span>
                                <select data-days-preset aria-label="Access period">
                                    @foreach ($presets as $value => $label)
                                        <option value="{{ $value }}" @selected($preset === (string) $value)>{{ $label }}</option>
                                    @endforeach
                                    <option value="custom" @selected($preset === 'custom')>Custom days…</option>
                                </select>
                                <input type="number" min="1" max="3650" name="plans[{{ $i }}][days]" value="{{ $days }}" placeholder="days" data-days @if ($preset !== 'custom') hidden @endif aria-label="Days">
                            </div>
                            <div class="field">
                                <span class="field-label"><span>Price</span></span>
                                <input type="number" min="0" step="0.01" name="plans[{{ $i }}][price]" value="{{ $row['price'] ?? '' }}" placeholder="49.00" data-price aria-label="Price">
                            </div>
                            <div class="field">
                                <span class="field-label"><span>Discount %</span></span>
                                <input type="number" min="0" max="100" name="plans[{{ $i }}][discount_percent]" value="{{ $row['discount_percent'] ?? '' }}" placeholder="0" data-discount aria-label="Discount percent">
                            </div>
                            <div class="field">
                                <span class="field-label"><span>Discount from</span></span>
                                <input type="date" name="plans[{{ $i }}][discount_starts_at]" value="{{ $row['discount_starts_at'] ?? '' }}" data-from aria-label="Discount starts">
                            </div>
                            <div class="field">
                                <span class="field-label"><span>until</span></span>
                                <input type="date" name="plans[{{ $i }}][discount_ends_at]" value="{{ $row['discount_ends_at'] ?? '' }}" data-to aria-label="Discount ends">
                            </div>
                            <div class="field">
                                <span class="field-label"><span>Name (optional)</span></span>
                                <input type="text" maxlength="60" name="plans[{{ $i }}][label]" value="{{ $row['label'] ?? '' }}" placeholder="e.g. Best value" aria-label="Plan name">
                            </div>
                        </div>
                        <div class="plan-foot">
                            <span class="plan-preview" data-preview></span>
                            <button type="button" class="btn btn-danger btn-sm" data-plan-remove>@include('admin.partials.icon', ['name' => 'trash']) Remove</button>
                        </div>
                    </div>
                @endforeach
            </div>
            <button type="button" class="btn btn-ghost btn-sm" data-plan-add>@include('admin.partials.icon', ['name' => 'plus']) Add another period</button>
            <p class="help" style="margin-top:10px">Discount dates are optional: empty = the discount always applies. Example: 1 month $15 · 6 months $79 with 15% off · 1 year $129 with 25% off until the end of the month. A plan without a price is removed.</p>
        </div>
    </div>
</section>

@push('scripts')
<script>
(() => {
    const card = document.getElementById('pricing');
    const list = card.querySelector('[data-plan-rows]');
    const paid = card.querySelector('[data-paid-only]');
    const currency = () => card.querySelector('[data-currency]').value;
    const money = (n) => currency() === 'KHR' ? Math.round(n).toLocaleString() + ' ៛' : '$' + n.toFixed(2);
    const today = new Date().toISOString().slice(0, 10);

    card.querySelector('[data-free-toggle]').addEventListener('change', (e) => { paid.hidden = e.target.checked; });

    const preview = (row) => {
        const price = parseFloat(row.querySelector('[data-price]').value);
        const pct = parseInt(row.querySelector('[data-discount]').value || '0', 10);
        const from = row.querySelector('[data-from]').value;
        const to = row.querySelector('[data-to]').value;
        const out = row.querySelector('[data-preview]');
        if (isNaN(price)) { out.textContent = 'Enter a price'; return; }
        const live = pct > 0 && (!from || from <= today) && (!to || to >= today);
        const final = pct > 0 ? price * (100 - pct) / 100 : price;
        let text = 'Students pay ' + money(live ? final : price);
        if (pct > 0) {
            text += live ? ` (−${pct}%, was ${money(price)})` : ` · ${money(final)} (−${pct}%) ${from && from > today ? 'from ' + from : 'ended'}`;
        }
        out.textContent = text;
        out.classList.toggle('is-discount', live);
    };
    const renumber = () => list.querySelectorAll('[data-plan-row]').forEach((row, i) => {
        row.querySelectorAll('[name^="plans["]').forEach((el) => { el.name = el.name.replace(/plans\[\d+]/, `plans[${i}]`); });
    });
    const syncDays = (row) => {
        const preset = row.querySelector('[data-days-preset]');
        const days = row.querySelector('[data-days]');
        if (preset.value === 'custom') { days.hidden = false; days.focus(); } else { days.hidden = true; days.value = preset.value; }
    };

    list.querySelectorAll('[data-plan-row]').forEach(preview);
    card.addEventListener('input', (e) => { const row = e.target.closest('[data-plan-row]'); if (row) preview(row); });
    card.querySelector('[data-currency]').addEventListener('change', () => list.querySelectorAll('[data-plan-row]').forEach(preview));
    list.addEventListener('change', (e) => { if (e.target.matches('[data-days-preset]')) syncDays(e.target.closest('[data-plan-row]')); });
    list.addEventListener('click', (e) => {
        if (!e.target.closest('[data-plan-remove]')) return;
        const row = e.target.closest('[data-plan-row]');
        if (list.children.length > 1) { row.remove(); renumber(); } else { row.querySelector('[data-price]').value = ''; preview(row); }
    });
    card.querySelector('[data-plan-add]').addEventListener('click', () => {
        const row = list.lastElementChild.cloneNode(true);
        row.querySelectorAll('input').forEach((input) => { if (input.type !== 'hidden' || input.matches('[data-plan-id]')) input.value = ''; });
        row.querySelector('[data-days-preset]').value = '30';
        syncDays(row);
        list.append(row);
        renumber();
        preview(row);
        row.querySelector('[data-price]').focus();
    });
})();
</script>
@endpush
