<?php

namespace App\Support;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Testing\Fakes\MailFake;

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
}
