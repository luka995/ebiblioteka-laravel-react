<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessage extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly array $data) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nova poruka sa eBiblioteka kontakt forme');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact-message');
    }

    public function attachments(): array
    {
        return [];
    }
}
