{{-- What the student paid for access given by hand: goes on their invoice (My account > Invoices). --}}
<div class="pay-fields">
    @include('admin.fields.input', ['name' => 'amount', 'label' => 'Amount paid', 'type' => 'number', 'value' => '', 'placeholder' => '0.00', 'prefix' => '$',
        'attrs' => 'min="0" step="0.01" inputmode="decimal" data-grant-amount', 'help' => 'Empty or 0 = free.'])
    <div class="field">
        <span class="field-label"><span>Paid by</span></span>
        <div class="pill-choice" role="radiogroup" aria-label="Paid by">
            @foreach (\App\Models\Invoice::METHODS as $key => $label)
                <label><input type="radio" name="payment_method" value="{{ $key }}" @checked(old('payment_method', 'cash') === $key)><span>{{ $label }}</span></label>
            @endforeach
        </div>
    </div>
    @include('admin.fields.input', ['name' => 'invoice_note', 'label' => 'Invoice note', 'value' => '', 'placeholder' => 'Optional, e.g. receipt no. 0042'])
</div>
