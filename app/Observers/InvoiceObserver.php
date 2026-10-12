<?php

namespace App\Observers;

use App\Enums\CommunicationTemplateKey;
use App\Enums\Status;
use App\Models\Invoice;
use App\Services\Communication\CommunicationAutomationService;
use App\Services\Email\InvoiceEmailService;
use App\Support\Data;
use Illuminate\Support\Facades\Log;

/**
 * Invoice observer.
 *
 * Responsible for triggering invoice-related side effects (emails).
 */
class InvoiceObserver
{
    public function __construct(
        private readonly CommunicationAutomationService $automationService,
        private readonly InvoiceEmailService $invoiceEmailService,
    ) {}

    /**
     * Handle the Invoice "created" event.
     */
    public function created(Invoice $invoice): void
    {
        if (! $this->automationService->shouldSend(CommunicationTemplateKey::InvoiceIssued)) {
            return;
        }

        $invoice->loadMissing('subscription.member');

        $email = $invoice->subscription && $invoice->subscription->member
            ? (string) $invoice->subscription->member->email
            : '';

        if (! filled($email)) {
            Log::info('Skipping invoice issued email: member email missing.', [
                'invoice_id' => $invoice->getKey(),
                'subscription_id' => $invoice->subscription_id,
            ]);

            return;
        }

        $this->invoiceEmailService->queueInvoiceIssuedEmail(
            invoiceId: Data::int($invoice->getKey()),
            toEmail: $email,
        );
    }

    /** Queue one overdue notice when an invoice first enters the overdue state. */
    public function becameOverdue(Invoice $invoice): void
    {
        if (
            $invoice->status !== Status::Overdue
            || ! $this->automationService->shouldSend(CommunicationTemplateKey::InvoiceOverdue)
        ) {
            return;
        }

        $invoice->loadMissing('subscription.member');
        $email = (string) ($invoice->subscription?->member?->email ?? '');

        if (! filled($email)) {
            Log::info('Skipping overdue invoice email: member email missing.', [
                'invoice_id' => $invoice->getKey(),
            ]);

            return;
        }

        $this->invoiceEmailService->queueOverdueReminderEmail(
            invoiceId: Data::int($invoice->getKey()),
            toEmail: $email,
        );
    }
}
