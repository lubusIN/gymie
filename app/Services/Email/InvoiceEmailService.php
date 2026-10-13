<?php

namespace App\Services\Email;

use App\Enums\CommunicationDeliveryStatus;
use App\Enums\CommunicationRetryHandler;
use App\Enums\CommunicationTemplateKey;
use App\Jobs\SendInvoiceIssuedEmail;
use App\Jobs\SendInvoicePaymentReceiptEmail;
use App\Jobs\SendInvoicePaymentReminderEmail;
use App\Mail\TestEmail;
use App\Models\CommunicationDelivery;
use App\Models\Invoice;
use App\Services\Communication\CommunicationDeliveryTracker;
use App\Services\Communication\EmailDeliveryContext;
use Illuminate\Support\Facades\Mail;
use LogicException;

/** Queue invoice messages and verify the outbound mail configuration. */
final class InvoiceEmailService
{
    public function __construct(
        private readonly EmailDeliveryContext $emailContext,
        private readonly CommunicationDeliveryTracker $deliveryTracker,
    ) {}

    /**
     * Queue an "invoice issued" email.
     */
    public function queueInvoiceIssuedEmail(int $invoiceId, string $toEmail, ?string $note = null, ?int $actorId = null): void
    {
        $delivery = $this->deliveryTracker->queued(
            CommunicationTemplateKey::InvoiceIssued,
            $toEmail,
            Invoice::query()->findOrFail($invoiceId),
            $actorId,
            [
                'handler' => CommunicationRetryHandler::InvoiceIssued->value,
                'invoice_id' => $invoiceId,
                'note' => $note,
            ],
        );

        if (! $this->canQueue($delivery)) {
            return;
        }

        SendInvoiceIssuedEmail::dispatch(
            invoiceId: $invoiceId,
            toEmail: $toEmail,
            note: $note,
            actorId: $actorId,
            deliveryId: $delivery->getKey(),
        )->afterCommit();
    }

    /**
     * Queue a payment reminder for an invoice with an outstanding balance.
     */
    public function queuePaymentReminderEmail(int $invoiceId, string $toEmail, ?string $note = null, ?int $actorId = null): void
    {
        $this->queueReminderEmail(
            templateKey: CommunicationTemplateKey::PaymentReminder,
            invoiceId: $invoiceId,
            toEmail: $toEmail,
            note: $note,
            actorId: $actorId,
        );
    }

    /** Queue the automatic reminder emitted when an invoice becomes overdue. */
    public function queueOverdueReminderEmail(int $invoiceId, string $toEmail): void
    {
        $this->queueReminderEmail(
            templateKey: CommunicationTemplateKey::InvoiceOverdue,
            invoiceId: $invoiceId,
            toEmail: $toEmail,
        );
    }

    /** Queue an invoice reminder using the requested communication template. */
    private function queueReminderEmail(
        CommunicationTemplateKey $templateKey,
        int $invoiceId,
        string $toEmail,
        ?string $note = null,
        ?int $actorId = null,
    ): void {
        $delivery = $this->deliveryTracker->queued(
            $templateKey,
            $toEmail,
            Invoice::query()->findOrFail($invoiceId),
            $actorId,
            [
                'handler' => CommunicationRetryHandler::PaymentReminder->value,
                'invoice_id' => $invoiceId,
                'note' => $note,
            ],
        );

        if (! $this->canQueue($delivery)) {
            return;
        }

        SendInvoicePaymentReminderEmail::dispatch(
            invoiceId: $invoiceId,
            toEmail: $toEmail,
            note: $note,
            actorId: $actorId,
            deliveryId: $delivery->getKey(),
        )->afterCommit();
    }

    /**
     * Queue a "payment received" receipt email.
     */
    public function queuePaymentReceiptEmail(int $invoiceId, int $transactionId, string $toEmail, ?string $note = null, ?int $actorId = null): void
    {
        $delivery = $this->deliveryTracker->queued(
            CommunicationTemplateKey::PaymentReceipt,
            $toEmail,
            Invoice::query()->findOrFail($invoiceId),
            $actorId,
            [
                'handler' => CommunicationRetryHandler::PaymentReceipt->value,
                'invoice_id' => $invoiceId,
                'invoice_transaction_id' => $transactionId,
                'note' => $note,
            ],
        );

        if (! $this->canQueue($delivery)) {
            return;
        }

        SendInvoicePaymentReceiptEmail::dispatch(
            invoiceId: $invoiceId,
            invoiceTransactionId: $transactionId,
            toEmail: $toEmail,
            note: $note,
            actorId: $actorId,
            deliveryId: $delivery->getKey(),
        )->afterCommit();
    }

    /**
     * Determine whether the selected mailer can deliver email outside the application.
     */
    public function isMailDeliveryConfigured(): bool
    {
        return $this->emailContext->isDeliveryConfigured();
    }

    /**
     * Send a synchronous message so an administrator can verify mail delivery.
     */
    public function sendTestEmail(string $toEmail): void
    {
        if (! $this->isMailDeliveryConfigured()) {
            throw new LogicException('Outbound mail delivery is not configured.');
        }

        if (! filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            throw new LogicException('The test email recipient is invalid.');
        }

        $settings = $this->emailContext->settings();

        $this->emailContext->withLocale($settings, function () use ($settings, $toEmail): void {
            $gym = $this->emailContext->identity($settings);
            $gymName = $gym['name'];
            $mailable = new TestEmail(
                fromAddress: $this->emailContext->fromAddress(),
                gymName: $gymName,
                gymEmail: $gym['email'],
                gymContact: $gym['contact'],
            );

            Mail::to($toEmail)->send($mailable);
        });
    }

    /** Mark an email as skipped when the mailer cannot deliver it. */
    private function canQueue(CommunicationDelivery $delivery): bool
    {
        if ($delivery->status === CommunicationDeliveryStatus::Skipped) {
            return false;
        }

        if ($this->emailContext->isDeliveryConfigured()) {
            return true;
        }

        $this->deliveryTracker->skipped($delivery->getKey(), $this->emailContext->unavailableReason());

        return false;
    }
}
