<?php

namespace App\Mail;

use App\Enums\SchoolYearNotificationType;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SchoolYearNotificationMail extends Mailable
{
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly User $recipient,
        public readonly SchoolYearNotificationType $type,
        public readonly array $data = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __($this->type->subjectKey(), $this->data),
            to: [new Address($this->recipient->email, $this->recipient->displayName())],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: $this->type->view(),
            text: $this->type->textView(),
            with: [
                'type' => $this->type,
                'data' => $this->data,
                'recipient' => $this->recipient,
            ],
        );
    }
}
