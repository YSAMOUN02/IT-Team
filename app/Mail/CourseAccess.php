<?php

namespace App\Mail;

use App\Models\Course;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sent when an admin adds a student or gives one a course: the course link, the sign-in page,
 * and for a new account the email + password to sign in with.
 */
class CourseAccess extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $student, public ?Course $course = null, public ?string $password = null)
    {
    }

    /**
     * Send it now and say what happened, for the admin's message (see note()):
     * "sent", "off" (mail only goes to the log / nowhere), "disabled" (account turned off: not sent) or "failed".
     */
    public static function sendTo(User $student, ?Course $course = null, ?string $password = null): string
    {
        if ($student->isDisabled()) {
            return 'disabled';
        }
        $faked = Mail::getFacadeRoot() instanceof \Illuminate\Support\Testing\Fakes\MailFake;
        if (! $faked && in_array(config('mail.default'), ['log', 'array'], true)) {
            return 'off';
        }
        try {
            // Students read the site in its main language (Khmer), whatever language the admin uses.
            Mail::to($student->email)->locale(config('app.locale'))->send(new self($student, $course, $password));

            return 'sent';
        } catch (Throwable $e) {
            Log::warning("Course access email to {$student->email} failed: ".$e->getMessage());

            return 'failed';
        }
    }

    /** " The student was emailed." and so on; empty when no email was asked for. */
    public static function note(?string $result): string
    {
        return match ($result) {
            'sent' => ' The student was emailed.',
            'off' => ' No email was sent: mail is not set up yet (MAIL_* in .env, see README). Pass the sign-in on yourself.',
            'disabled' => ' No email was sent because this account is turned off.',
            'failed' => ' The email could not be sent (mail server refused or did not answer). Pass the sign-in on yourself.',
            default => '',
        };
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->course
            ? __('account.mail_access_subject', ['course' => $this->course->title])
            : __('account.mail_welcome_subject'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.course-access');
    }
}
