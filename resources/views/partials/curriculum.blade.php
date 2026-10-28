{{--
    Course outline (modules -> lessons / tests).
    $outline, $course, $hasAccess (bool), optional $progress (CourseProgress), optional $current (model)
--}}
@php
    $progress = $progress ?? null;
    $current = $current ?? null;
    $moduleNumber = 0;
@endphp
<div class="curriculum">
    @foreach ($outline as $section)
        @php
            $module = $section['module'];
            if ($module) { $moduleNumber++; }
            // For students: how much of this module is done (lessons ticked, tests passed).
            $moduleTotal = $section['items']->count();
            $moduleDone = $progress ? $section['items']->filter(fn ($i) => $progress->itemDone($i))->count() : 0;
            $moduleComplete = $progress && $moduleTotal && $moduleDone === $moduleTotal;
        @endphp
        <details @class(['cur-module', 'is-complete' => $moduleComplete]) @if ($loop->first || ($current && $section['items']->contains(fn ($i) => $i::class === $current::class && $i->id === $current->id))) open @endif>
            <summary>
                <span class="cur-num">
                    @if ($moduleComplete)
                        <svg viewBox="0 0 24 24" aria-label="{{ __('account.module_done') }}"><path d="m6 12 4 4 8-9"/></svg>
                    @else
                        {{ $module ? str_pad($moduleNumber, 2, '0', STR_PAD_LEFT) : '··' }}
                    @endif
                </span>
                <span class="cur-title">
                    <small>{{ $module ? __('account.module', ['number' => $moduleNumber]) : __('account.not_in_module') }}</small>
                    {{ $module?->title ?? __('account.not_in_module') }}
                    @if ($progress && $moduleTotal)
                        <span class="cur-bar" aria-hidden="true"><span style="width: {{ round($moduleDone * 100 / $moduleTotal) }}%"></span></span>
                    @endif
                </span>
                <span class="cur-count">{{ $progress ? $moduleDone.'/'.$moduleTotal : $moduleTotal }}</span>
            </summary>
            @if ($module?->description)
                <p class="cur-desc">{{ $module->description }}</p>
            @endif
            <ol class="cur-items">
                @foreach ($section['items'] as $item)
                    @php
                        $isQuiz = $item instanceof \App\Models\Quiz;
                        $open = $hasAccess || $item->is_preview;
                        $done = $progress && ($isQuiz ? $progress->quizPassed($item->id) : $progress->lessonDone($item->id));
                        $isCurrent = $current && $current::class === $item::class && $current->id === $item->id;
                        $url = $isQuiz ? route('learn.quiz', [$course->slug, $item->slug]) : route('learn.lesson', [$course->slug, $item->slug]);
                    @endphp
                    <li @class(['cur-item', 'is-done' => $done, 'is-current' => $isCurrent, 'is-locked' => ! $open, 'is-quiz' => $isQuiz])>
                        <span class="cur-icon" aria-hidden="true">
                            @if ($done)
                                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/></svg>
                            @elseif ($isQuiz)
                                <svg viewBox="0 0 24 24"><path d="M9 4h6l1 2h3v14H5V6h3z"/><path d="m9 13 2 2 4-4"/></svg>
                            @else
                                <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m10.5 9.5 4 2.5-4 2.5z"/></svg>
                            @endif
                        </span>
                        @if ($open)
                            <a href="{{ $url }}">{{ $item->title }}</a>
                        @else
                            <span>{{ $item->title }}</span>
                        @endif
                        <span class="cur-meta">
                            @if ($isQuiz)
                                <em class="tag tag-{{ $item->type }}">{{ $item->typeLabel() }}</em>
                            @elseif ($item->duration)
                                {{ $item->duration }}
                            @endif
                            @if (! $hasAccess && $item->is_preview)
                                <em class="tag tag-preview">{{ __('account.preview') }}</em>
                            @elseif (! $open)
                                <svg class="lock" viewBox="0 0 24 24" aria-label="{{ __('account.locked') }}"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ol>
        </details>
    @endforeach
</div>
