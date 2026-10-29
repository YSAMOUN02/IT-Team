{{-- Out of tries without passing: ask the teacher for another try, or see their answer. $course, $quiz, $retake (latest request or null), $canRequestRetake --}}
@if ($retake?->isPending())
    <p class="alert alert-info">{{ __('account.retake_pending', ['date' => $retake->created_at->format('d/m/Y')]) }}</p>
@else
    @if ($retake?->status === 'declined')
        <div class="alert alert-error">
            {{ __('account.retake_declined') }}
            @if ($retake->reply)<br><strong>{{ __('account.teacher_note') }}:</strong> {{ $retake->reply }}@endif
        </div>
    @endif
    @if ($canRequestRetake)
        <form class="retake-request" id="retake" method="post" action="{{ route('learn.quiz.retake-request', [$course->slug, $quiz->slug]) }}">
            @csrf
            <h3>{{ __('account.retake_ask_title') }}</h3>
            <p>{{ __('account.retake_ask_text') }}</p>
            <label class="field">
                <span>{{ __('account.retake_message') }}</span>
                <textarea name="message" rows="3" maxlength="1000"></textarea>
            </label>
            <div><button class="btn btn-primary">{{ __('account.retake_ask') }}</button></div>
        </form>
    @endif
@endif
