<?php

namespace App\Mail;

use App\Models\LibraryRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LibraryRequestReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly LibraryRequest $libraryRequest)
    {
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.library_request.subject', ['school' => $this->libraryRequest->school_name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.library-request',
            text: 'emails.library-request-text',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
