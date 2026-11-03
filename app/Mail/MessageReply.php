<?php

namespace App\Mail;

use App\Models\InquiryReply;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A reply to a contact-form message, written in Admin > Messages: the admin's text, the shared
 * signature, and the original message quoted below. Answers come back to the site's email.
 */
class MessageReply extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public InquiryReply $reply)
    {
    }

    public function envelope(): Envelope
    {
        $back = setting('email') ?: config('mail.from.address');

        return new Envelope(
            subject: $this->reply->subject,
            replyTo: $back ? [new Address($back, setting('site_name', config('app.name')))] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.message-reply', text: 'emails.message-reply-text');
    }

    /** Plain text as safe HTML: everything escaped, line breaks kept, web and email addresses clickable. */
    public static function textToHtml(?string $text): string
    {
        $parts = preg_split(
            '~(https?://[^\s<>"\']*[^\s<>"\'.,;:!?)\]]|[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,})~iu',
            (string) $text, -1, PREG_SPLIT_DELIM_CAPTURE
        );

        $html = '';
        foreach ($parts as $i => $part) {
            // Odd pieces are the addresses the pattern captured.
            $html .= $i % 2 === 0
                ? e($part)
                : '<a href="'.e(str_contains($part, '://') ? $part : 'mailto:'.$part).'" style="color:#5f59f7">'.e($part).'</a>';
        }

        return nl2br($html, false);
    }
}
