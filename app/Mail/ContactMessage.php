<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessage extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly array $data)
    {
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('emails.contact.subject'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-message',
            text: 'emails.contact-message-text',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
