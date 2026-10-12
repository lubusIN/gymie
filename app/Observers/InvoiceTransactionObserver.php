<?php

namespace App\Observers;

use App\Enums\CommunicationTemplateKey;
use App\Models\InvoiceTransaction;
use App\Services\Communication\CommunicationAutomationService;
use App\Services\Email\InvoiceEmailService;
use App\Support\Data;
use Illuminate\Support\Facades\Log;

/**
 * Invoice transaction observer.
 *
 * Responsible for triggering receipt emails on payment transactions.
 */
class InvoiceTransactionObserver
{
    public function __construct(
        private readonly CommunicationAutomationService $automationService,
        private readonly InvoiceEmailService $invoiceEmailService,
    ) {}

    /**
     * Handle the InvoiceTransaction "created" event.
     */
    public function created(InvoiceTransaction $invoiceTransaction): void
    {
        if ($invoiceTransaction->type !== 'payment') {
            return;
        }

        if (($invoiceTransaction->note ?? null) === 'Initial payment') {
            return;
        }

        if (! $this->automationService->shouldSend(CommunicationTemplateKey::PaymentReceipt)) {
            return;
        }

        $invoiceTransaction->loadMissing('invoice.subscription.member');

        $invoiceId = $invoiceTransaction->invoice?->getKey();
        if (! $invoiceId) {
            return;
        }

        $invoice = $invoiceTransaction->invoice;

        $email = $invoice->subscription && $invoice->subscription->member
            ? (string) $invoice->subscription->member->email
            : '';

        if (! filled($email)) {
            Log::info('Skipping payment receipt email: member email missing.', [
                'invoice_id' => $invoiceId,
                'invoice_transaction_id' => $invoiceTransaction->getKey(),
            ]);

            return;
        }

        $this->invoiceEmailService->queuePaymentReceiptEmail(
            invoiceId: Data::int($invoiceId),
            transactionId: Data::int($invoiceTransaction->getKey()),
            toEmail: $email,
        );
    }

    // Other lifecycle events intentionally left unhandled for v1.
}
