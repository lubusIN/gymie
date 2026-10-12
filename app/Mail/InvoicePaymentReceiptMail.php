<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use App\Support\Invoices\InvoiceDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invoice payment receipt email (member-facing).
 *
 * Confirms a payment was received and attaches the updated invoice PDF.
 */
class InvoicePaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  non-empty-string  $gymName
     */
    public function __construct(
        public readonly Invoice $invoice,
        public readonly InvoiceTransaction $transaction,
        public readonly string $subjectLine,
        public readonly string $messageBody,
        public readonly string $fromAddress,
        public readonly string $gymName,
        public readonly string $gymEmail,
        public readonly string $gymContact,
        public readonly string $memberName,
        public readonly ?string $note,
        public readonly string $pdfBytes,
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
            subject: $this->subjectLine,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.invoices.payment-received',
            with: [
                'invoice' => $this->invoice,
                'transaction' => $this->transaction,
                'gymName' => $this->gymName,
                'gymEmail' => $this->gymEmail,
                'gymContact' => $this->gymContact,
                'memberName' => $this->memberName,
                'messageBody' => $this->messageBody,
                'note' => $this->note,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfBytes, InvoiceDocument::pdfFilename($this->invoice))
                ->withMime('application/pdf'),
        ];
    }
}
