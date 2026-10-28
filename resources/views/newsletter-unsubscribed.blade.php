@extends('layouts.site')

@section('title', __('site.unsubscribed_title'))

@section('content')
    <section class="section coming-soon">
        <div class="container narrow">
            <h1>{{ __('site.unsubscribed_title') }}</h1>
            <p class="lead">{{ __('site.unsubscribed_text', ['email' => $email]) }}</p>
            <a class="btn btn-primary" href="{{ route('home') }}">{{ __('site.back_home') }}</a>
        </div>
    </section>
@endsection
