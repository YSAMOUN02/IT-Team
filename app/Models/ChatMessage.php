<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in a student's conversation with the school. The student writes from My account > Chat;
 * admins answer from Admin > Chat, by chat or by email (then the subject and signature are kept too).
 */
class ChatMessage extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'from_student' => 'boolean',
        'emailed_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** Written by the school, not seen by the student yet. */
    public function scopeUnreadByStudent(Builder $query): Builder
    {
        return $query->where('from_student', false)->whereNull('read_at');
    }

    /** Written by a student, not seen by any admin yet. */
    public function scopeUnreadByAdmins(Builder $query): Builder
    {
        return $query->where('from_student', true)->whereNull('read_at');
    }

    /** How many students are waiting for an admin to read their messages. */
    public static function studentsWaiting(): int
    {
        return static::unreadByAdmins()->distinct()->count('student_id');
    }

    /** Clean text: Windows line ends, and any bytes that are not UTF-8 (they would break the save). */
    public static function clean(?string $text): string
    {
        return trim(mb_convert_encoding(str_replace("\r\n", "\n", (string) $text), 'UTF-8', 'UTF-8'));
    }

    /** The messages of a conversation after a given id, ready for the open page to add. */
    public static function payload(User $student, int $after, string $viewer): array
    {
        $messages = $student->chatMessages()->with('sender:id,name')->where('id', '>', $after)->orderBy('id')->get();

        return [
            'html' => $messages->isEmpty() ? '' : view('partials.chat-messages', ['chatMessages' => $messages, 'chatViewer' => $viewer, 'chatStudent' => $student])->render(),
            'last_id' => $messages->last()?->id ?? $after,
        ];
    }
}
