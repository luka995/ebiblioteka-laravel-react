<?php

namespace App\Services\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class TransactionalMailService
{
    private const SUPPORTED_LOCALES = ['sr-Cyrl', 'sr-Latn', 'en'];

    private const FALLBACK_LOCALE = 'sr-Cyrl';

    public function send(Mailable $mail): void
    {
        Mail::mailer(config('mail.transactional_mailer'))
            ->send($mail->locale($this->currentLocale()));
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
        if (in_array($recipient->locale, self::SUPPORTED_LOCALES, true)) {
            return $recipient->locale;
        }

        return $this->currentLocale();
    }

    private function currentLocale(): string
    {
        $locale = app()->getLocale();

        return in_array($locale, self::SUPPORTED_LOCALES, true)
            ? $locale
            : self::FALLBACK_LOCALE;
    }
}
