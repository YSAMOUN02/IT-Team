{{-- Result of one attempt: passed / not passed, or not submitted (still taking it, or the time ran out). --}}
@if (! $attempt->isSubmitted())
    @if ($attempt->isOverdue())
        <span class="status status-off">Time ran out</span>
    @else
        <span class="status status-sched">In progress</span>
    @endif
@elseif ($attempt->passed)
    <span class="status status-on">Passed</span>
@else
    <span class="status status-blocked">Not passed</span>
@endif
