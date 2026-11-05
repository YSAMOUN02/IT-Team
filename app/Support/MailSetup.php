<?php

namespace App\Support;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Testing\Fakes\MailFake;
use Throwable;

/** Whether emails really leave this server (MAIL_* in .env), rather than only going to the log. */
class MailSetup
{
    public static function ready(): bool
    {
        if (Mail::getFacadeRoot() instanceof MailFake) {
            return true; // tests
        }

        return ! in_array(config('mail.default'), ['log', 'array'], true);
    }

    /** Why a send failed, in words an admin can act on (the full error goes to the log). */
    public static function reason(Throwable $e): string
    {
        $error = $e->getMessage();

        return match (true) {
            str_contains($error, 'authenticate') || str_contains($error, '535') => 'the mail server refused the sign-in. Check MAIL_USERNAME and the app password (MAIL_PASSWORD) in .env',
            str_contains($error, 'Connection could not be established') || str_contains($error, 'timed out') => 'this server could not reach the mail server. The hosting may block the port (MAIL_HOST and MAIL_PORT in .env)',
            default => 'the mail server refused or did not answer',
        };
    }
}
