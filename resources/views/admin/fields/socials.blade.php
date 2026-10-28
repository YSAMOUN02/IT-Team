{{-- Repeatable social accounts: platform + link, in display order. --}}
@php
    $platforms = config('socials');
    $rows = old('socials') ?? array_map(fn ($l) => ['platform' => $l['platform'], 'url' => $l['url']], social_links());
    if (! $rows) {
        $rows = [['platform' => 'facebook', 'url' => '']];
    }
@endphp
<div class="field">
    <span class="field-label"><span>{{ $label }}</span></span>
    @error('socials.*')<span class="error-text">{{ $message }}</span>@enderror
    <div class="social-rows" data-social-rows>
        @foreach ($rows as $i => $row)
            @php
                $meta = $platforms[$row['platform'] ?? ''] ?? $platforms['website'];
            @endphp
            <div class="social-row" data-social-row>
                <span class="social-ico" style="background: {{ $meta['color'] }}" data-social-ico>
                    <svg viewBox="0 0 24 24" aria-hidden="true">{!! $meta['icon'] !!}</svg>
                </span>
                <select name="socials[{{ $i }}][platform]" aria-label="Platform" data-social-platform>
                    @foreach ($platforms as $key => $p)
                        <option value="{{ $key }}" data-color="{{ $p['color'] }}" data-placeholder="{{ $p['placeholder'] }}" @selected(($row['platform'] ?? '') === $key)>{{ $p['label'] }}</option>
                    @endforeach
                </select>
                <input type="url" name="socials[{{ $i }}][url]" value="{{ $row['url'] ?? '' }}" placeholder="{{ $meta['placeholder'] }}" aria-label="Link">
                <button type="button" class="btn btn-ghost btn-sm btn-icon" data-social-up title="Move up" aria-label="Move up">↑</button>
                <button type="button" class="btn btn-ghost btn-sm btn-icon" data-social-down title="Move down" aria-label="Move down">↓</button>
                <button type="button" class="btn btn-danger btn-sm btn-icon" data-social-remove title="Remove" aria-label="Remove">@include('admin.partials.icon', ['name' => 'trash'])</button>
            </div>
        @endforeach
    </div>
    <div>
        <button type="button" class="btn btn-ghost btn-sm" data-social-add>@include('admin.partials.icon', ['name' => 'plus']) Add account</button>
    </div>
    <span class="help">Use the full link of each page or profile. WhatsApp: https://wa.me/855… (number without +). An empty link is removed when you save.</span>
</div>

<template id="social-icons">
    @foreach ($platforms as $key => $p)
        <svg data-icon="{{ $key }}" viewBox="0 0 24 24" aria-hidden="true">{!! $p['icon'] !!}</svg>
    @endforeach
</template>

@once
    @push('scripts')
        <script>
        (() => {
            const list = document.querySelector('[data-social-rows]');
            if (!list) return;
            const icons = document.getElementById('social-icons').content;

            // Rename inputs to socials[0..n] so the saved order matches the screen.
            const renumber = () => list.querySelectorAll('[data-social-row]').forEach((row, i) => {
                row.querySelectorAll('[name^="socials["]').forEach((el) => { el.name = el.name.replace(/socials\[\d+]/, `socials[${i}]`); });
            });
            const paint = (row) => {
                const option = row.querySelector('[data-social-platform]').selectedOptions[0];
                const ico = row.querySelector('[data-social-ico]');
                ico.style.background = option.dataset.color;
                ico.replaceChildren(icons.querySelector(`[data-icon="${option.value}"]`).cloneNode(true));
                row.querySelector('input[type=url]').placeholder = option.dataset.placeholder;
            };

            list.addEventListener('change', (e) => { if (e.target.matches('[data-social-platform]')) paint(e.target.closest('[data-social-row]')); });
            list.addEventListener('click', (e) => {
                const row = e.target.closest('[data-social-row]');
                if (!row) return;
                if (e.target.closest('[data-social-remove]')) {
                    if (list.children.length > 1) { row.remove(); } else { row.querySelector('input').value = ''; }
                } else if (e.target.closest('[data-social-up]') && row.previousElementSibling) {
                    list.insertBefore(row, row.previousElementSibling);
                } else if (e.target.closest('[data-social-down]') && row.nextElementSibling) {
                    list.insertBefore(row.nextElementSibling, row);
                }
                renumber();
            });
            document.querySelector('[data-social-add]').addEventListener('click', () => {
                const row = list.lastElementChild.cloneNode(true);
                row.querySelector('input').value = '';
                const used = Array.from(list.querySelectorAll('[data-social-platform]')).map((s) => s.value);
                const select = row.querySelector('[data-social-platform]');
                select.value = Array.from(select.options).map((o) => o.value).find((v) => !used.includes(v)) || 'website';
                list.append(row);
                paint(row);
                renumber();
                row.querySelector('input').focus();
            });
        })();
        </script>
    @endpush
@endonce
