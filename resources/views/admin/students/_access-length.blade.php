{{--
    How long access lasts, for "Give access" (course page and student page): the course's plans as
    one-click buttons (fill days + amount), or days typed by hand. Empty = the course's first plan.
    $planCourses: the course(s) whose plans to offer; with several, the group for the course picked
    in [data-course-select] is shown.
--}}
@php
    $planLength = fn (?int $days) => match (true) {
        ! $days => 'Lifetime',
        $days % 365 === 0 => ($days / 365).' '.str('year')->plural($days / 365),
        $days % 30 === 0 => ($days / 30).' '.str('month')->plural($days / 30),
        default => $days.' days',
    };
@endphp
<input type="hidden" name="lifetime" value="{{ old('lifetime', '0') }}" data-lifetime>
@foreach ($planCourses as $planCourse)
    @if ($planCourse->plans->isNotEmpty())
        <div class="plan-chips" role="group" aria-label="Plans" data-plans-for="{{ $planCourse->id }}" @unless ($loop->first) hidden @endunless>
            @foreach ($planCourse->plans as $plan)
                <button type="button" class="plan-chip" aria-pressed="false" data-plan
                        data-days="{{ $plan->days }}" data-price="{{ number_format($plan->currentPrice(), 2, '.', '') }}">
                    <b>{{ $planLength($plan->days) }}</b>
                    <small>{{ $plan->formattedPrice() }}</small>
                </button>
            @endforeach
        </div>
    @endif
@endforeach
<div class="field">
    <label class="field-label" for="f-days-{{ $formKey ?? 'grant' }}"><span>Days of access</span></label>
    <input id="f-days-{{ $formKey ?? 'grant' }}" type="number" name="days" min="1" max="3650" step="1" value="{{ old('days') }}" placeholder="e.g. 90" data-days style="max-width:160px">
    <span class="help">Pick a plan, or type the number of days. Empty = the course’s first plan (lifetime if it has none). Adds to access that is still running.</span>
</div>
