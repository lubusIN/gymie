<?php

namespace App\Services\Communication;

use App\Enums\CommunicationDeliveryStatus;
use App\Enums\CommunicationRetryHandler;
use App\Jobs\SendCommunicationEmail;
use App\Jobs\SendInvoiceIssuedEmail;
use App\Jobs\SendInvoicePaymentReceiptEmail;
use App\Jobs\SendInvoicePaymentReminderEmail;
use App\Models\CommunicationDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class RetryCommunicationDelivery
{
    public function __construct(
        private readonly EmailDeliveryContext $emailContext,
    ) {}

    /** Requeue a permanently failed delivery using its encrypted context. */
    public function handle(CommunicationDelivery $delivery): void
    {
        DB::transaction(function () use ($delivery): void {
            $lockedDelivery = CommunicationDelivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->getKey());

            if (! $lockedDelivery->canBeRetried()) {
                throw ValidationException::withMessages([
                    'delivery' => __('app.communication.validation.delivery_not_retryable'),
                ]);
            }

            if (! $this->emailContext->isDeliveryConfigured()) {
                throw ValidationException::withMessages([
                    'delivery' => $this->emailContext->unavailableReason(),
                ]);
            }

            ['handler' => $handler, 'payload' => $payload] = $this->validatedPayload($lockedDelivery);

            $lockedDelivery->update([
                'subject' => null,
                'status' => CommunicationDeliveryStatus::Queued,
                'queued_at' => now(),
                'sent_at' => null,
                'failed_at' => null,
                'failure_reason' => null,
            ]);

            $this->dispatch($lockedDelivery, $handler, $payload);
        });
    }

    /**
     * Validate the internal retry contract before creating a queue job.
     *
     * @return array{handler: CommunicationRetryHandler, payload: array<string, mixed>}
     */
    private function validatedPayload(CommunicationDelivery $delivery): array
    {
        $payload = $delivery->retry_payload;

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'delivery' => __('app.communication.validation.delivery_not_retryable'),
            ]);
        }

        $handlerValue = $payload['handler'] ?? null;
        $handler = is_string($handlerValue)
            ? CommunicationRetryHandler::tryFrom($handlerValue)
            : null;

        if ($handler === null) {
            $this->throwNotRetryable();
        }

        $rules = match ($handler) {
            CommunicationRetryHandler::Communication => [
                'variables' => ['present', 'array'],
            ],
            CommunicationRetryHandler::InvoiceIssued, CommunicationRetryHandler::PaymentReminder => [
                'invoice_id' => ['required', 'integer', 'min:1'],
                'note' => ['nullable', 'string'],
            ],
            CommunicationRetryHandler::PaymentReceipt => [
                'invoice_id' => ['required', 'integer', 'min:1'],
                'invoice_transaction_id' => ['required', 'integer', 'min:1'],
                'note' => ['nullable', 'string'],
            ],
        };

        Validator::make(
            [
                ...$payload,
                'recipient' => $delivery->recipient,
            ],
            [
                ...$rules,
                'recipient' => ['required', 'email:rfc'],
            ],
        )->validate();

        return [
            'handler' => $handler,
            'payload' => $payload,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function dispatch(
        CommunicationDelivery $delivery,
        CommunicationRetryHandler $handler,
        array $payload,
    ): void {
        $deliveryId = (int) $delivery->getKey();
        $actorId = $delivery->actor_id;

        match ($handler) {
            CommunicationRetryHandler::Communication => SendCommunicationEmail::dispatch(
                deliveryId: $deliveryId,
            )->afterCommit(),
            CommunicationRetryHandler::InvoiceIssued => SendInvoiceIssuedEmail::dispatch(
                invoiceId: (int) $payload['invoice_id'],
                toEmail: $delivery->recipient,
                note: $payload['note'] ?? null,
                actorId: $actorId,
                deliveryId: $deliveryId,
            )->afterCommit(),
            CommunicationRetryHandler::PaymentReminder => SendInvoicePaymentReminderEmail::dispatch(
                invoiceId: (int) $payload['invoice_id'],
                toEmail: $delivery->recipient,
                note: $payload['note'] ?? null,
                actorId: $actorId,
                deliveryId: $deliveryId,
            )->afterCommit(),
            CommunicationRetryHandler::PaymentReceipt => SendInvoicePaymentReceiptEmail::dispatch(
                invoiceId: (int) $payload['invoice_id'],
                invoiceTransactionId: (int) $payload['invoice_transaction_id'],
                toEmail: $delivery->recipient,
                note: $payload['note'] ?? null,
                actorId: $actorId,
                deliveryId: $deliveryId,
            )->afterCommit(),
        };
    }

    private function throwNotRetryable(): never
    {
        throw ValidationException::withMessages([
            'delivery' => __('app.communication.validation.delivery_not_retryable'),
        ]);
    }
}
