{{-- Page title band used at the top of inner pages. --}}
<section class="page-header">
    <div class="container">
        @isset($eyebrow)
            <p class="eyebrow">{{ $eyebrow }}</p>
        @endisset
        <h1>{{ $title }}</h1>
        @if (! empty($text))
            <div class="lead">{!! rich_text($text) !!}</div>
        @endif
        @if (! empty($buttons))
            <div class="btn-row">
                @foreach ($buttons as $i => [$label, $url])
                    @if ($label)
                        <a class="btn {{ $i ? 'btn-outline' : 'btn-primary' }}" href="{{ link_url($url) }}">{{ $label }}</a>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</section>
