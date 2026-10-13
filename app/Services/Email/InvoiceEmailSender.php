<?php

namespace App\Services\Email;

use App\Enums\CommunicationDeliveryStatus;
use App\Enums\CommunicationRetryHandler;
use App\Enums\CommunicationTemplateKey;
use App\Helpers\Helpers;
use App\Mail\InvoiceIssuedMail;
use App\Mail\InvoicePaymentReceiptMail;
use App\Models\CommunicationDelivery;
use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use App\Services\Communication\CommunicationDeliveryTracker;
use App\Services\Communication\CommunicationPreferenceService;
use App\Services\Communication\CommunicationTemplateRenderer;
use App\Services\Communication\EmailDeliveryContext;
use App\Support\Data;
use App\Support\Invoices\InvoiceDocument;
use App\Support\Invoices\InvoicePdfRenderer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Deliver invoice messages and record their final outcome. */
final class InvoiceEmailSender
{
    public function __construct(
        private readonly InvoicePdfRenderer $renderer,
        private readonly EmailDeliveryContext $emailContext,
        private readonly CommunicationTemplateRenderer $templateRenderer,
        private readonly CommunicationDeliveryTracker $deliveryTracker,
        private readonly CommunicationPreferenceService $preferenceService,
    ) {}

    /** Send an "invoice issued" email with its PDF attached. */
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

    /** Send a payment reminder with the latest invoice PDF attached. */
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

    /** Send a payment receipt email with its invoice PDF attached. */
    public function sendPaymentReceiptEmail(
        int $invoiceId,
        int $transactionId,
        string $toEmail,
        ?string $note = null,
        ?int $deliveryId = null,
    ): void {
        $invoice = InvoiceDocument::loadForRendering(Invoice::query()->findOrFail($invoiceId));
        $delivery = $this->resolveDelivery(
            $deliveryId,
            CommunicationTemplateKey::PaymentReceipt,
            $toEmail,
            $invoice,
            [
                'handler' => CommunicationRetryHandler::PaymentReceipt->value,
                'invoice_id' => $invoiceId,
                'invoice_transaction_id' => $transactionId,
                'note' => $note,
            ],
        );

        if ($delivery === null) {
            return;
        }

        $deliveryId = (int) $delivery->getKey();
        $transaction = InvoiceTransaction::query()
            ->whereKey($transactionId)
            ->where('invoice_id', $invoice->getKey())
            ->firstOrFail();
        $memberName = $this->memberName($invoice);

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
                $rendered = $this->templateRenderer->render(
                    $delivery->template,
                    [
                        ...$this->invoiceTemplateVariables($invoice, $gym['name'], $memberName),
                        'payment_amount' => Helpers::formatCurrency((float) ($transaction->amount ?? 0)),
                    ],
                );

                Mail::to($toEmail)->send(new InvoicePaymentReceiptMail(
                    invoice: $invoice,
                    transaction: $transaction,
                    subjectLine: $rendered['subject'],
                    messageBody: $rendered['body'],
                    fromAddress: $this->emailContext->fromAddress(),
                    gymName: $gym['name'],
                    gymEmail: $gym['email'],
                    gymContact: $gym['contact'],
                    memberName: $memberName,
                    note: $note,
                    pdfBytes: $this->renderer->render($invoice),
                ));

                $this->deliveryTracker->sent($deliveryId, $rendered['subject']);
            });
        } catch (Throwable $exception) {
            $this->deliveryTracker->failed($deliveryId, $exception);

            throw $exception;
        }
    }

    /** Send an invoice document email using the requested message purpose. */
    private function sendInvoiceDocumentEmail(
        int $invoiceId,
        string $toEmail,
        ?string $note,
        CommunicationTemplateKey $templateKey,
        ?int $deliveryId = null,
    ): void {
        $invoice = InvoiceDocument::loadForRendering(Invoice::query()->findOrFail($invoiceId));
        $delivery = $this->resolveDelivery(
            $deliveryId,
            $templateKey,
            $toEmail,
            $invoice,
            [
                'handler' => $templateKey === CommunicationTemplateKey::PaymentReminder
                    ? CommunicationRetryHandler::PaymentReminder->value
                    : CommunicationRetryHandler::InvoiceIssued->value,
                'invoice_id' => $invoiceId,
                'note' => $note,
            ],
        );

        if ($delivery === null) {
            return;
        }

        $deliveryId = (int) $delivery->getKey();
        $memberName = $this->memberName($invoice);

        if ($templateKey === CommunicationTemplateKey::PaymentReminder && ! $this->hasOutstandingBalance($invoice)) {
            Log::info('Skipping payment reminder email: invoice no longer has an outstanding balance.', [
                'invoice_id' => $invoiceId,
            ]);
            $this->deliveryTracker->skipped($deliveryId, 'Invoice no longer has an outstanding balance.');

            return;
        }

        if (! $this->isValidRecipientEmail($toEmail, ['invoice_id' => $invoiceId])) {
            $this->deliveryTracker->skipped($deliveryId, 'Recipient email is invalid.');

            return;
        }

        $settings = $this->emailContext->settings();

        try {
            $this->emailContext->withLocale($settings, function () use ($settings, $invoice, $toEmail, $note, $memberName, $templateKey, $delivery, $deliveryId): void {
                $gym = $this->emailContext->identity($settings);
                $rendered = $this->templateRenderer->render(
                    $delivery->template,
                    $this->invoiceTemplateVariables($invoice, $gym['name'], $memberName),
                );
                $messageNote = $templateKey === CommunicationTemplateKey::PaymentReminder && blank($note)
                    ? __('app.emails.payment_reminder_note')
                    : $note;

                Mail::to($toEmail)->send(new InvoiceIssuedMail(
                    invoice: $invoice,
                    subjectLine: $rendered['subject'],
                    messageBody: $rendered['body'],
                    fromAddress: $this->emailContext->fromAddress(),
                    gymName: $gym['name'],
                    gymEmail: $gym['email'],
                    gymContact: $gym['contact'],
                    memberName: $memberName,
                    note: $messageNote,
                    pdfBytes: $this->renderer->render($invoice),
                ));

                $this->deliveryTracker->sent($deliveryId, $rendered['subject']);
            });
        } catch (Throwable $exception) {
            $this->deliveryTracker->failed($deliveryId, $exception);

            throw $exception;
        }
    }

    /** @param array<string, mixed> $retryPayload */
    private function resolveDelivery(
        ?int $deliveryId,
        CommunicationTemplateKey $templateKey,
        string $toEmail,
        Invoice $invoice,
        array $retryPayload,
    ): ?CommunicationDelivery {
        $delivery = $deliveryId === null
            ? $this->deliveryTracker->queued(
                $templateKey,
                $toEmail,
                $invoice,
                $this->authenticatedUserId(),
                $retryPayload,
            )
            : CommunicationDelivery::query()->with(['template', 'related'])->find($deliveryId);

        if ($delivery === null || ! $this->canDeliver($delivery)) {
            return null;
        }

        $delivery->loadMissing('template');

        return $delivery;
    }

    /** Mark a delivery as skipped when it cannot be sent. */
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

    /** @param array<string, mixed> $context */
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

    /** @return array<string, string> */
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

    private function hasOutstandingBalance(Invoice $invoice): bool
    {
        return (float) ($invoice->due_amount ?? 0) > 0
            && in_array($invoice->status?->value, ['issued', 'partial', 'overdue'], true);
    }

    private function memberName(Invoice $invoice): string
    {
        return (string) ($invoice->subscription?->member?->name ?? '');
    }

    private function authenticatedUserId(): ?int
    {
        $userId = auth()->id();

        return is_numeric($userId) ? (int) $userId : null;
    }
}
