{{-- The one shared signature: shown as it will look; "Edit" changes it for this and later replies. --}}
<div class="signature-box" data-signature>
    <div class="signature-head">
        <span class="field-label"><span>Signature</span></span>
        <button type="button" class="btn btn-ghost btn-sm" data-signature-edit>@include('admin.partials.icon', ['name' => 'edit']) Edit</button>
    </div>
    <div class="signature-preview" data-signature-preview>{{ old('signature', $signature) }}</div>
    <div class="signature-editor" data-signature-editor hidden>
        <textarea name="signature" rows="5" maxlength="2000" data-signature-input aria-label="Signature">{{ old('signature', $signature) }}</textarea>
        <span class="help">One signature for every reply. A change is kept for the next replies too (also in Settings › Email replies).</span>
    </div>
</div>
