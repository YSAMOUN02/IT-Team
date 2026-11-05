{{-- One course of a class: each student's progress, result, last activity and best score per test. --}}
<div class="table-wrap">
    <table class="data gradebook class-book">
        <thead>
        <tr>
            <th>Student</th>
            <th>Progress</th>
            <th class="hide-sm">Result</th>
            <th class="hide-sm">Last active</th>
            @foreach ($tests as $test)
                <th class="test hide-sm" title="{{ $test->title }}"><a href="{{ route('admin.results.index', ['quiz' => $test->id, 'class' => $classroom->id]) }}">{{ $labels[$test->id] }}</a><small>pass {{ $test->pass_percent }}%</small></th>
            @endforeach
            <th class="hide-sm"><span class="sr-only">Remove</span></th>
        </tr>
        </thead>
        <tbody>
        @foreach ($shown as $row)
            @php
                $student = $row['student'];
            @endphp
            <tr @class(['needs-help' => in_array('help', $row['groups'], true)])>
                <td>
                    <div class="row-title">
                        <span class="avatar" aria-hidden="true">{{ $initials($student->name) }}</span>
                        <div>
                            <a href="{{ route('admin.students.show', $student) }}">{{ $student->name }}</a>
                            @if ($student->isDisabled())<span class="status status-blocked">Account off</span>@endif
                            <span class="row-sub">{{ $student->phone ?: $student->email }}</span>
                            @if ($row['reason'])
                                <span class="help-reason">{{ $row['reason'] }}</span>
                            @endif
                            @if ($row['asked'])
                                <a class="row-sub" href="{{ route('admin.retake-requests.index') }}">Asked for a re-exam →</a>
                            @endif
                            <span class="row-sub show-sm"><span class="status {{ $tones[$row['status']] }}">{{ $row['label'] }}</span> · {{ $row['last_active'] ? 'active '.$row['last_active']->diffForHumans() : 'no activity yet' }}</span>
                            <span class="row-sub show-sm class-contact"><a href="{{ route('admin.chats.show', $student) }}">Chat</a> · <a href="{{ route('admin.chats.show', [$student, 'via' => 'email']) }}">Email</a></span>
                        </div>
                    </div>
                </td>
                <td style="min-width:110px">
                    @if ($row['enrollment'] && $row['status'] !== 'ended')
                        <div class="mini-progress"><span style="width: {{ $row['progress']->percent }}%"></span></div>
                        <span class="row-sub">{{ $row['progress']->percent }}% · {{ $row['progress']->done }}/{{ $row['progress']->total }}</span>
                    @else
                        <a class="give-link" href="{{ $giveAccess($course, $student) }}">Give access →</a>
                    @endif
                </td>
                <td class="hide-sm">
                    <span class="status {{ $tones[$row['status']] }}">{{ $row['label'] }}</span>
                    @if ($row['enrollment']?->certificate_code)<span class="row-sub mono">{{ $row['enrollment']->certificate_code }}</span>@endif
                </td>
                <td class="hide-sm nowrap">
                    @if ($row['last_active'])
                        <span title="{{ $row['last_active']->format('d M Y, H:i') }}">{{ $row['last_active']->diffForHumans() }}</span>
                    @else
                        <span class="muted">{{ $row['enrollment'] ? 'Not yet' : '—' }}</span>
                    @endif
                </td>
                @foreach ($tests as $test)
                    @php $score = $row['scores'][$test->id] ?? null; @endphp
                    <td class="test hide-sm">
                        @if ($score)
                            <a @class(['grade', 'grade-pass' => $score['passed'], 'grade-fail' => ! $score['passed']]) href="{{ route('admin.results.show', $score['best']) }}"
                               title="{{ $test->title }}: best {{ $score['best']->percent }}%, {{ $score['passed'] ? 'passed' : 'not passed yet' }}, {{ $score['tries'] }} {{ str('try')->plural($score['tries']) }}">{{ $score['best']->percent }}%</a>
                            @if ($score['tries'] > 1)<small>{{ $score['tries'] }} tries</small>@endif
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                @endforeach
                <td class="actions hide-sm">
                    @include('admin.classes._contact')
                    @include('admin.classes._remove', ['student' => $student])
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
