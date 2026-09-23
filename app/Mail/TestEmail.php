<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Synchronous email used to verify the configured outbound mail transport.
 */
class TestEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $fromAddress,
        public readonly string $gymName,
        public readonly string $gymEmail,
        public readonly string $gymContact,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromAddress, $this->gymName),
            replyTo: filter_var($this->gymEmail, FILTER_VALIDATE_EMAIL)
                ? [new Address($this->gymEmail, $this->gymName)]
                : [],
            subject: __('app.emails.test_subject', ['gym_name' => $this->gymName]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.test',
            with: [
                'gymName' => $this->gymName,
                'gymEmail' => $this->gymEmail,
                'gymContact' => $this->gymContact,
            ],
        );
    }
}
