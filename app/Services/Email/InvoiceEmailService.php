<?php

namespace App\Services\Email;

use App\Enums\CommunicationDeliveryStatus;
use App\Enums\CommunicationTemplateKey;
use App\Helpers\Helpers;
use App\Jobs\SendInvoiceIssuedEmail;
use App\Jobs\SendInvoicePaymentReceiptEmail;
use App\Jobs\SendInvoicePaymentReminderEmail;
use App\Mail\InvoiceIssuedMail;
use App\Mail\InvoicePaymentReceiptMail;
use App\Mail\TestEmail;
use App\Models\CommunicationDelivery;
use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use App\Services\Communication\CommunicationDeliveryTracker;
use App\Services\Communication\CommunicationPreferenceService;
use App\Services\Communication\CommunicationTemplateRenderer;
use App\Services\Communication\EmailDeliveryContext;
use App\Services\Communication\RetryCommunicationDelivery;
use App\Support\Data;
use App\Support\Invoices\InvoiceDocument;
use App\Support\Invoices\InvoicePdfRenderer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Throwable;

/**
 * Invoice email service.
 *
 * This service is responsible for:
 * - building invoice/payment email payloads
 * - rendering PDF attachments
 * - configuring the sender identity and Reply-To from gym settings
 * - rendering safe, token-based communication templates
 * - recording delivery outcomes
 */
final class InvoiceEmailService
{
    public function __construct(
        private readonly InvoicePdfRenderer $renderer,
        private readonly EmailDeliveryContext $emailContext,
        private readonly CommunicationTemplateRenderer $templateRenderer,
        private readonly CommunicationDeliveryTracker $deliveryTracker,
        private readonly CommunicationPreferenceService $preferenceService,
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
                'handler' => RetryCommunicationDelivery::INVOICE_ISSUED_HANDLER,
                'invoice_id' => $invoiceId,
                'note' => $note,
            ],
        );

        if (! $this->canDeliver($delivery)) {
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
                'handler' => RetryCommunicationDelivery::PAYMENT_REMINDER_HANDLER,
                'invoice_id' => $invoiceId,
                'note' => $note,
            ],
        );

        if (! $this->canDeliver($delivery)) {
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
                'handler' => RetryCommunicationDelivery::PAYMENT_RECEIPT_HANDLER,
                'invoice_id' => $invoiceId,
                'invoice_transaction_id' => $transactionId,
                'note' => $note,
            ],
        );

        if (! $this->canDeliver($delivery)) {
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

    /**
     * Send an "invoice issued" email with PDF attached.
     */
    public function sendInvoiceIssuedEmail(int $invoiceId, string $toEmail, ?string $note = null, ?int $deliveryId = null): void
    {
        $this->sendInvoiceDocumentEmail(
            invoiceId: $invoiceId,
            toEmail: $toEmail,
            note: $note,
            templateKey: CommunicationTemplateKey::InvoiceIssued,
            deliveryId: $deliveryId,
        );
    }

    /**
     * Send a payment reminder with the latest invoice PDF attached.
     */
    public function sendPaymentReminderEmail(int $invoiceId, string $toEmail, ?string $note = null, ?int $deliveryId = null): void
    {
        $this->sendInvoiceDocumentEmail(
            invoiceId: $invoiceId,
            toEmail: $toEmail,
            note: $note,
            templateKey: CommunicationTemplateKey::PaymentReminder,
            deliveryId: $deliveryId,
        );
    }

    /**
     * Send an invoice document email using the requested message purpose.
     */
    private function sendInvoiceDocumentEmail(
        int $invoiceId,
        string $toEmail,
        ?string $note = null,
        CommunicationTemplateKey $templateKey = CommunicationTemplateKey::InvoiceIssued,
        ?int $deliveryId = null,
    ): void {
        $invoice = InvoiceDocument::loadForRendering(Invoice::query()->findOrFail($invoiceId));
        $delivery = $deliveryId === null
            ? $this->deliveryTracker->queued(
                $templateKey,
                $toEmail,
                $invoice,
                $this->authenticatedUserId(),
                [
                    'handler' => $templateKey === CommunicationTemplateKey::PaymentReminder
                        ? RetryCommunicationDelivery::PAYMENT_REMINDER_HANDLER
                        : RetryCommunicationDelivery::INVOICE_ISSUED_HANDLER,
                    'invoice_id' => $invoiceId,
                    'note' => $note,
                ],
            )
            : $this->findDelivery($deliveryId);

        if ($delivery === null || ! $this->canDeliver($delivery)) {
            return;
        }

        $delivery->loadMissing('template');
        $deliveryId = (int) $delivery->getKey();

        $memberName = $invoice->subscription && $invoice->subscription->member
            ? (string) $invoice->subscription->member->name
            : '';

        if (
            $templateKey === CommunicationTemplateKey::PaymentReminder
            && (
                (float) ($invoice->due_amount ?? 0) <= 0
                || ! in_array($invoice->status?->value, ['issued', 'partial', 'overdue'], true)
            )
        ) {
            Log::info('Skipping payment reminder email: invoice no longer has an outstanding balance.', [
                'invoice_id' => $invoiceId,
            ]);
            $this->deliveryTracker->skipped($deliveryId, 'Invoice no longer has an outstanding balance.');

            return;
        }

        if (! $this->isValidRecipientEmail($toEmail, [
            'invoice_id' => $invoiceId,
        ])) {
            $this->deliveryTracker->skipped($deliveryId, 'Recipient email is invalid.');

            return;
        }

        $settings = $this->emailContext->settings();

        try {
            $this->emailContext->withLocale($settings, function () use ($settings, $invoice, $toEmail, $note, $memberName, $templateKey, $delivery, $deliveryId): void {
                $gym = $this->emailContext->identity($settings);
                $gymName = $gym['name'];
                $rendered = $this->templateRenderer->render(
                    $delivery->template,
                    $this->invoiceTemplateVariables($invoice, $gymName, $memberName),
                );
                $messageNote = $templateKey === CommunicationTemplateKey::PaymentReminder && blank($note)
                    ? __('app.emails.payment_reminder_note')
                    : $note;

                $mailable = new InvoiceIssuedMail(
                    invoice: $invoice,
                    subjectLine: $rendered['subject'],
                    messageBody: $rendered['body'],
                    fromAddress: $this->emailContext->fromAddress(),
                    gymName: $gymName,
                    gymEmail: $gym['email'],
                    gymContact: $gym['contact'],
                    memberName: $memberName,
                    note: $messageNote,
                    pdfBytes: $this->renderer->render($invoice),
                );

                Mail::to($toEmail)->send($mailable);
                $this->deliveryTracker->sent($deliveryId, $rendered['subject']);
            });
        } catch (Throwable $exception) {
            $this->deliveryTracker->failed($deliveryId, $exception);

            throw $exception;
        }
    }

    /**
     * Send a payment receipt email with PDF attached.
     */
    public function sendPaymentReceiptEmail(
        int $invoiceId,
        int $transactionId,
        string $toEmail,
        ?string $note = null,
        ?int $deliveryId = null,
    ): void {
        $invoice = InvoiceDocument::loadForRendering(Invoice::query()->findOrFail($invoiceId));
        $delivery = $deliveryId === null
            ? $this->deliveryTracker->queued(
                CommunicationTemplateKey::PaymentReceipt,
                $toEmail,
                $invoice,
                $this->authenticatedUserId(),
                [
                    'handler' => RetryCommunicationDelivery::PAYMENT_RECEIPT_HANDLER,
                    'invoice_id' => $invoiceId,
                    'invoice_transaction_id' => $transactionId,
                    'note' => $note,
                ],
            )
            : $this->findDelivery($deliveryId);

        if ($delivery === null || ! $this->canDeliver($delivery)) {
            return;
        }

        $delivery->loadMissing('template');
        $deliveryId = (int) $delivery->getKey();

        $transaction = InvoiceTransaction::query()
            ->whereKey($transactionId)
            ->where('invoice_id', $invoice->getKey())
            ->firstOrFail();

        $memberName = $invoice->subscription && $invoice->subscription->member
            ? (string) $invoice->subscription->member->name
            : '';

        if (! $this->isValidRecipientEmail($toEmail, [
            'invoice_id' => $invoiceId,
            'invoice_transaction_id' => $transactionId,
        ])) {
            $this->deliveryTracker->skipped($deliveryId, 'Recipient email is invalid.');

            return;
        }

        $settings = $this->emailContext->settings();

        try {
            $this->emailContext->withLocale($settings, function () use ($settings, $invoice, $transaction, $toEmail, $note, $memberName, $delivery, $deliveryId): void {
                $gym = $this->emailContext->identity($settings);
                $gymName = $gym['name'];
                $rendered = $this->templateRenderer->render(
                    $delivery->template,
                    [
                        ...$this->invoiceTemplateVariables($invoice, $gymName, $memberName),
                        'payment_amount' => Helpers::formatCurrency((float) ($transaction->amount ?? 0)),
                    ],
                );

                $mailable = new InvoicePaymentReceiptMail(
                    invoice: $invoice,
                    transaction: $transaction,
                    subjectLine: $rendered['subject'],
                    messageBody: $rendered['body'],
                    fromAddress: $this->emailContext->fromAddress(),
                    gymName: $gymName,
                    gymEmail: $gym['email'],
                    gymContact: $gym['contact'],
                    memberName: $memberName,
                    note: $note,
                    pdfBytes: $this->renderer->render($invoice),
                );

                Mail::to($toEmail)->send($mailable);
                $this->deliveryTracker->sent($deliveryId, $rendered['subject']);
            });
        } catch (Throwable $exception) {
            $this->deliveryTracker->failed($deliveryId, $exception);

            throw $exception;
        }
    }

    /**
     * Validate an email address and log a warning when invalid.
     *
     * @param  array<string, mixed>  $context
     */
    private function isValidRecipientEmail(string $toEmail, array $context): bool
    {
        if (filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        Log::warning('Skipping invoice email: invalid recipient email.', [
            ...$context,
            'to_email' => $toEmail,
        ]);

        return false;
    }

    /**
     * Build the common subject tokens used by invoice + receipt emails.
     *
     * @return array<string, string>
     */
    private function invoiceTemplateVariables(Invoice $invoice, string $gymName, string $memberName): array
    {
        return [
            'invoice_number' => Data::string($invoice->number),
            'status' => $invoice->getDisplayStatusLabel(),
            'total' => Helpers::formatCurrency((float) ($invoice->total_amount ?? 0)),
            'paid' => Helpers::formatCurrency((float) ($invoice->paid_amount ?? 0)),
            'due' => Helpers::formatCurrency((float) ($invoice->due_amount ?? 0)),
            'gym_name' => $gymName,
            'member_name' => $memberName,
            'due_date' => $invoice->due_date?->format('M j, Y') ?? '',
        ];
    }

    /**
     * Return the authenticated actor when a message is sent synchronously.
     */
    private function authenticatedUserId(): ?int
    {
        $userId = auth()->id();

        return is_numeric($userId) ? (int) $userId : null;
    }

    /** Mark a delivery as skipped when the configured mailer cannot send externally. */
    private function canDeliver(CommunicationDelivery $delivery): bool
    {
        if ($delivery->status === CommunicationDeliveryStatus::Skipped) {
            return false;
        }

        $delivery->loadMissing('related');
        $deliveryId = (int) $delivery->getKey();

        $optOutReason = $this->preferenceService->emailOptOutReason($delivery->related);

        if ($delivery->channel === 'email' && $optOutReason !== null) {
            $this->deliveryTracker->skipped($deliveryId, $optOutReason);

            return false;
        }

        if ($this->emailContext->isDeliveryConfigured()) {
            return true;
        }

        $this->deliveryTracker->skipped($deliveryId, $this->emailContext->unavailableReason());

        return false;
    }

    /** Load a queued delivery with the context required to send it. */
    private function findDelivery(int $deliveryId): ?CommunicationDelivery
    {
        return CommunicationDelivery::query()
            ->with(['template', 'related'])
            ->find($deliveryId);
    }
}
