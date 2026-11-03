{{-- What the student paid for access given by hand: goes on their invoice (My account > Invoices). --}}
@include('admin.fields.input', ['name' => 'amount', 'label' => 'Amount paid (USD)', 'type' => 'number', 'value' => '', 'placeholder' => '0', 'attrs' => 'min="0" step="0.01"', 'help' => 'Empty or 0 = free. Shown on the student’s invoice.'])
<div class="field">
    <label class="field-label" for="f-payment_method"><span>Paid by</span></label>
    <select id="f-payment_method" name="payment_method">
        @foreach (\App\Models\Invoice::METHODS as $key => $label)
            <option value="{{ $key }}" @selected(old('payment_method', 'cash') === $key)>{{ $label }}</option>
        @endforeach
    </select>
</div>
@include('admin.fields.input', ['name' => 'invoice_note', 'label' => 'Invoice note (optional)', 'value' => '', 'placeholder' => 'e.g. receipt no. 0042'])
