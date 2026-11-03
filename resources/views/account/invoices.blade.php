@extends('layouts.site')

@section('title', __('account.my_invoices'))
@section('no_ads', true)

@section('content')
    <section class="account-head">
        <div class="container">
            <p class="eyebrow">{{ auth()->user()->name }}</p>
            <h1>{{ __('account.my_invoices') }}</h1>
            @include('account._tabs')
        </div>
    </section>

    <section class="section section-flush-top">
        <div class="container">
            @if ($invoices->isEmpty())
                <div class="empty-box">
                    <p>{{ __('account.invoices_empty') }}</p>
                    <a class="btn btn-primary" href="{{ route('courses.index') }}">{{ __('account.browse_courses') }}</a>
                </div>
            @else
                <div class="card table-card">
                    <table class="simple-table">
                        <thead><tr><th>{{ __('account.invoice_number') }}</th><th>{{ __('account.course') }}</th><th>{{ __('account.invoice_access') }}</th><th>{{ __('account.amount') }}</th><th>{{ __('account.status') }}</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td class="mono">{{ $invoice->number }}<small>{{ $invoice->issued_at->format('d/m/Y') }}</small></td>
                                <td>{{ $invoice->course_title }}</td>
                                <td>
                                    {{ $invoice->access_days ? __('account.invoice_days', ['days' => $invoice->access_days]) : __('account.invoice_lifetime') }}
                                    @if ($invoice->access_until)<small>{{ __('account.invoice_until', ['date' => $invoice->access_until->format('d/m/Y')]) }}</small>@endif
                                </td>
                                <td>{{ $invoice->formattedAmount() }}</td>
                                <td><span class="pill pill-{{ $invoice->status }}">{{ __('account.invoice_status_'.$invoice->status) }}</span></td>
                                <td><a href="{{ route('account.invoices.show', $invoice) }}">{{ __('account.invoice_view') }} →</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
@endsection
