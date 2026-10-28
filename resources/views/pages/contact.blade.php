@extends('layouts.site')

@section('title', $page->title)
@section('admin_edit', route('admin.pages.edit', $page))
@section('description', $page->meta_description ?: strip_tags((string) $page->field('intro.text')))

@php
    $phone = setting('phone');
    $email = setting('email');
    $socials = social_links();
    $chat = collect($socials)->firstWhere('chat', true); // first chat app in the admin's list
    $cards = [
        'phone' => [$phone, $phone ? 'tel:'.preg_replace('/[^\d+]/', '', $phone) : null, '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>'],
        'email' => [$email, $email ? 'mailto:'.$email : null, '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>'],
        'chat' => [$chat['label'] ?? null, $chat['url'] ?? null, $chat['icon'] ?? ''],
    ];
@endphp

@section('content')
    @include('partials.page-header', [
        'title' => $page->field('intro.title', $page->title),
        'text' => $page->field('intro.text'),
    ])

    <section class="section">
        <div class="container contact-layout">
            <div class="card form-card">
                @include('partials.contact-form')
            </div>

            <div class="contact-cards">
                @foreach ($cards as $key => [$value, $href, $icon])
                    @if ($value && $href)
                        <div class="card contact-card">
                            <span class="contact-icon"><svg viewBox="0 0 24 24" aria-hidden="true">{!! $icon !!}</svg></span>
                            <div>
                                <h2>{{ $page->field("$key.title", __('site.'.($key === 'chat' ? 'chat' : ($key === 'phone' ? 'call' : 'write')))) }}</h2>
                                @if ($page->field("$key.text"))
                                    <p>{{ $page->field("$key.text") }}</p>
                                @endif
                                <a href="{{ $href }}" @if ($key === 'chat') target="_blank" rel="noopener" @endif>{{ $value }}</a>
                            </div>
                        </div>
                    @endif
                @endforeach

                @if ($socials)
                    <div class="card contact-card follow-card">
                        <div>
                            <h2>{{ __('site.follow_us') }}</h2>
                            <ul class="follow-list">
                                @foreach ($socials as $social)
                                    <li>
                                        <a href="{{ $social['url'] }}" target="_blank" rel="noopener me" style="--brand: {{ $social['color'] }}">
                                            <span class="follow-ico"><svg viewBox="0 0 24 24" aria-hidden="true">{!! $social['icon'] !!}</svg></span>
                                            {{ $social['label'] }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
