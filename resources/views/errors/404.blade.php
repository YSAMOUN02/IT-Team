@extends('layouts.site')

@section('title', __('site.not_found'))

@section('content')
    <section class="section coming-soon">
        <div class="container narrow">
            <p class="error-code">404</p>
            <h1>{{ __('site.not_found') }}</h1>
            <p class="lead">{{ __('site.not_found_text') }}</p>
            <div class="btn-row btn-row-center">
                <a class="btn btn-primary" href="{{ route('home') }}">{{ __('site.back_home') }}</a>
                <a class="btn btn-outline" href="{{ route('tutorials.index') }}">{{ __('site.tutorials') }}</a>
            </div>
        </div>
    </section>
@endsection
