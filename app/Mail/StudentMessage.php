<?php

namespace App\Mail;

use App\Models\ChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * An email an admin writes to a student from Admin > Chat: the text, the shared signature and a
 * link to the chat. Answers by email come back to the site's address.
 */
class StudentMessage extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ChatMessage $chatMessage)
    {
    }

    public function envelope(): Envelope
    {
        $back = setting('email') ?: config('mail.from.address');

        return new Envelope(
            subject: $this->chatMessage->subject,
            replyTo: $back ? [new Address($back, setting('site_name', config('app.name')))] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.student-message', text: 'emails.student-message-text');
    }
}
