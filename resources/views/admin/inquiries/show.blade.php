@extends('admin.layout')

@section('title', $inquiry->subject)
@section('crumbs')
    <span aria-hidden="true">/</span><a href="{{ route('admin.inquiries.index') }}">Messages</a>
    <span aria-hidden="true">/</span><span>Message</span>
@endsection
@section('actions')
    <a class="btn btn-primary" href="mailto:{{ $inquiry->email }}?subject={{ rawurlencode('Re: '.$inquiry->subject) }}">@include('admin.partials.icon', ['name' => 'mail']) Reply by email</a>
    <form method="post" action="{{ route('admin.inquiries.destroy', $inquiry) }}" data-confirm="Delete this message from {{ $inquiry->name }}?">
        @csrf @method('DELETE')
        <button class="btn btn-danger">@include('admin.partials.icon', ['name' => 'trash']) Delete</button>
    </form>
@endsection

@section('content')
    <div class="form-grid reveal">
        <section class="card">
            <div class="card-head">
                <div style="display:flex;align-items:center;gap:12px">
                    <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($inquiry->name, 0, 1)) }}</span>
                    <div><h2>{{ $inquiry->name }}</h2><p>{{ $inquiry->created_at->format('l, j F Y \a\t H:i') }} · {{ $inquiry->created_at->diffForHumans() }}</p></div>
                </div>
            </div>
            <div class="card-body">
                <div class="message-body">{{ $inquiry->message }}</div>
            </div>
        </section>

        <div class="form-side">
            <section class="card">
                <div class="card-head"><h2>Contact</h2></div>
                <div class="card-body">
                    <dl class="meta-list">
                        <dt>Email</dt><dd><a href="mailto:{{ $inquiry->email }}">{{ $inquiry->email }}</a></dd>
                        @if ($inquiry->phone)
                            <dt>Phone</dt><dd><a href="tel:{{ preg_replace('/[^\d+]/', '', $inquiry->phone) }}">{{ $inquiry->phone }}</a></dd>
                        @endif
                        <dt>Read</dt><dd>{{ $inquiry->read_at?->format('d M Y, H:i') }}</dd>
                    </dl>
                </div>
                @if ($inquiry->phone)
                    <div class="card-foot"><a class="btn btn-ghost btn-block" href="tel:{{ preg_replace('/[^\d+]/', '', $inquiry->phone) }}">@include('admin.partials.icon', ['name' => 'phone']) Call {{ $inquiry->phone }}</a></div>
                @endif
            </section>
        </div>
    </div>
@endsection
