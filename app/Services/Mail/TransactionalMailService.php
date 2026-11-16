<?php

namespace App\Services\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class TransactionalMailService
{
    public function send(Mailable $mail): void
    {
        Mail::mailer(config('mail.transactional_mailer'))
            ->send($mail->locale(app()->getLocale()));
    }

    /**
     * Uses Laravel's normal behavior: mailables implementing ShouldQueue are queued;
     * other mailables are sent immediately. Uses the recipient's locale when available.
     */
    public function sendFor(User $recipient, Mailable $mail): void
    {
        Mail::mailer(config('mail.transactional_mailer'))
            ->send($mail->locale($this->recipientLocale($recipient)));
    }

    /**
     * Sends immediately, even when the mailable implements ShouldQueue.
     * Use this inside an already queued job to avoid queueing the mailable again.
     */
    public function sendNowFor(User $recipient, Mailable $mail): void
    {
        Mail::mailer(config('mail.transactional_mailer'))
            ->sendNow($mail->locale($this->recipientLocale($recipient)));
    }

    private function recipientLocale(User $recipient): string
    {
        return in_array($recipient->locale, ['sr-Cyrl', 'sr-Latn', 'en'], true)
            ? $recipient->locale
            : app()->getLocale();
    }
}
