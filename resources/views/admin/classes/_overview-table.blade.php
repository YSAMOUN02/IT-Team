{{-- A class with several courses: one row per student, one column per course (progress and status). --}}
<div class="table-wrap">
    <table class="data class-book class-overview">
        <thead>
        <tr>
            <th>Student</th>
            @foreach ($courses as $column)
                <th class="hide-sm"><a href="{{ route('admin.classes.show', [$classroom, 'course' => $column->id]) }}" title="See this course’s tests">{{ $column->title }}</a></th>
            @endforeach
            <th class="hide-sm">Last active</th>
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
                            @foreach ($row['reasons'] as $reason)
                                <span class="help-reason">{{ $reason }}</span>
                            @endforeach
                            {{-- Phones: one line per course instead of the columns. --}}
                            <span class="show-sm overview-lines">
                                @foreach ($courses as $column)
                                    @php $cell = $row['cells'][$column->id]; @endphp
                                    <span class="row-sub">{{ $column->title }}: <span class="status {{ $tones[$cell['status']] }}">{{ $cell['enrollment'] && $cell['status'] !== 'ended' ? $cell['progress']->percent.'% · ' : '' }}{{ $cell['label'] }}</span>@unless ($cell['enrollment'] && $cell['status'] !== 'ended') <a class="give-link" href="{{ $giveAccess($column, $student) }}">Give access →</a>@endunless</span>
                                @endforeach
                                <span class="row-sub class-contact"><a href="{{ route('admin.chats.show', $student) }}">Chat</a> · <a href="{{ route('admin.chats.show', [$student, 'via' => 'email']) }}">Email</a></span>
                            </span>
                        </div>
                    </div>
                </td>
                @foreach ($courses as $column)
                    @php $cell = $row['cells'][$column->id]; @endphp
                    <td class="hide-sm overview-cell">
                        @if ($cell['enrollment'] && $cell['status'] !== 'ended')
                            <div class="mini-progress"><span style="width: {{ $cell['progress']->percent }}%"></span></div>
                            <span class="row-sub">{{ $cell['progress']->percent }}% · <span class="status {{ $tones[$cell['status']] }}">{{ $cell['label'] }}</span></span>
                        @else
                            <span class="status {{ $tones[$cell['status']] }}">{{ $cell['label'] }}</span>
                            <a class="give-link" href="{{ $giveAccess($column, $student) }}">Give access →</a>
                        @endif
                    </td>
                @endforeach
                <td class="hide-sm nowrap">
                    @if ($row['last_active'])
                        <span title="{{ $row['last_active']->format('d M Y, H:i') }}">{{ $row['last_active']->diffForHumans() }}</span>
                    @else
                        <span class="muted">Not yet</span>
                    @endif
                </td>
                <td class="actions hide-sm">
                    @include('admin.classes._contact')
                    @include('admin.classes._remove', ['student' => $student])
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
