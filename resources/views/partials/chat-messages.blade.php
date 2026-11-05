{{-- Chat bubbles, on the student's page (site.css) and the admin's (admin.css). The student always sees "the school"; admins see who wrote. --}}
@foreach ($chatMessages as $chatMessage)
    @php
        $chatMine = $chatViewer === 'student' ? $chatMessage->from_student : ! $chatMessage->from_student;
        $chatWho = match (true) {
            $chatViewer === 'student' => $chatMessage->from_student ? null : setting('site_name', config('app.name')),
            $chatMessage->from_student => $chatStudent->name,
            (int) $chatMessage->sender_id === (int) auth()->id() => 'You',
            default => $chatMessage->sender?->name ?: 'Admin',
        };
    @endphp
    <div @class(['chat-msg', 'chat-msg-mine' => $chatMine, 'chat-msg-email' => $chatMessage->emailed_at]) data-id="{{ $chatMessage->id }}">
        <div class="chat-bubble">
            @if ($chatMessage->emailed_at)
                <p class="chat-email-head">{{ $chatViewer === 'admin' ? 'Sent by email' : __('account.chat_by_email') }}</p>
                <p class="chat-subject">{{ $chatMessage->subject }}</p>
            @endif
            <div class="chat-text">{!! \App\Mail\MessageReply::textToHtml($chatMessage->body) !!}</div>
            @if ($chatMessage->signature)
                <div class="chat-signature">{{ $chatMessage->signature }}</div>
            @endif
        </div>
        <p class="chat-meta">{{ $chatWho ? $chatWho.' · ' : '' }}<time datetime="{{ $chatMessage->created_at->toIso8601String() }}">{{ $chatMessage->created_at->format($chatMessage->created_at->isToday() ? 'H:i' : 'd/m/Y H:i') }}</time></p>
    </div>
@endforeach
