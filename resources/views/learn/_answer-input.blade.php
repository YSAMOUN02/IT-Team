{{-- The answer area of a short answer, matching, ordering or drag & drop question. Plain form fields first
     (they work without JavaScript); exam.js turns ordering and drag & drop into drag-and-tap pieces. --}}
@php
    $qid = $question->id;
@endphp
@switch ($question->type)
    @case('short')
        <div class="q-short" data-part="text">
            <input type="text" name="answers[{{ $qid }}]" maxlength="500" autocomplete="off" spellcheck="false"
                   placeholder="{{ __('account.answer_placeholder') }}" aria-label="{{ __('account.answer_placeholder') }}">
        </div>
        @break

    @case('matching')
        @php($rights = $question->shuffledFor($attempt->id, $question->options, 'right'))
        <div class="q-match">
            @foreach ($question->options as $left)
                <label class="q-match-row" data-part="{{ $left->id }}">
                    <span class="q-match-left">{{ $left->text }}</span>
                    <span class="q-match-arrow" aria-hidden="true">→</span>
                    <select name="answers[{{ $qid }}][{{ $left->id }}]">
                        <option value="">{{ __('account.choose') }}</option>
                        @foreach ($rights as $right)
                            <option value="{{ $right->id }}">{{ $right->match_text }}</option>
                        @endforeach
                    </select>
                </label>
            @endforeach
        </div>
        @break

    @case('ordering')
        <ol class="q-order" data-order>
            @foreach ($question->shuffledFor($attempt->id, $question->options) as $item)
                <li class="q-order-item" data-id="{{ $item->id }}" data-part="{{ $loop->index }}">
                    <input type="hidden" name="answers[{{ $qid }}][]" value="{{ $item->id }}">
                    <span class="q-order-handle" title="{{ __('account.drag_to_move') }}" aria-hidden="true">⠿</span>
                    <span class="q-order-num" aria-hidden="true"></span>
                    <span class="q-order-text">{{ $item->text }}</span>
                    <span class="q-order-moves">
                        <button type="button" data-move="-1" aria-label="{{ __('account.move_up') }}">↑</button>
                        <button type="button" data-move="1" aria-label="{{ __('account.move_down') }}">↓</button>
                    </span>
                </li>
            @endforeach
        </ol>
        @break

    @case('dragdrop')
        @php($words = $question->shuffledFor($attempt->id, $question->options))
        <div class="q-bank" data-bank aria-label="{{ __('account.words') }}">
            @foreach ($words as $word)
                <button type="button" class="q-chip" data-chip="{{ $word->id }}">{{ $word->text }}</button>
            @endforeach
        </div>
        @break
@endswitch
