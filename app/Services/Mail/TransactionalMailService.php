<?php

namespace App\Services\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class TransactionalMailService
{
    public function send(Mailable $mail): void
    {
        Mail::mailer(config('mail.transactional_mailer'))
            ->send($mail->locale(app()->getLocale()));
    }
}
