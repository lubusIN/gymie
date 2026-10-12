<?php

namespace App\Services\Communication;

use App\Enums\CommunicationDeliveryStatus;
use App\Jobs\SendCommunicationEmail;
use App\Jobs\SendInvoiceIssuedEmail;
use App\Jobs\SendInvoicePaymentReceiptEmail;
use App\Jobs\SendInvoicePaymentReminderEmail;
use App\Models\CommunicationDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class RetryCommunicationDelivery
{
    public const string COMMUNICATION_HANDLER = 'communication';

    public const string INVOICE_ISSUED_HANDLER = 'invoice_issued';

    public const string PAYMENT_REMINDER_HANDLER = 'payment_reminder';

    public const string PAYMENT_RECEIPT_HANDLER = 'payment_receipt';

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

            $payload = $this->validatedPayload($lockedDelivery);

            $lockedDelivery->update([
                'subject' => null,
                'status' => CommunicationDeliveryStatus::Queued,
                'queued_at' => now(),
                'sent_at' => null,
                'failed_at' => null,
                'failure_reason' => null,
            ]);

            $this->dispatch($lockedDelivery, $payload);
        });
    }

    /**
     * Validate the internal retry contract before creating a queue job.
     *
     * @return array<string, mixed>
     */
    private function validatedPayload(CommunicationDelivery $delivery): array
    {
        $payload = $delivery->retry_payload;

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'delivery' => __('app.communication.validation.delivery_not_retryable'),
            ]);
        }

        $handler = data_get($payload, 'handler');
        $rules = match ($handler) {
            self::COMMUNICATION_HANDLER => [
                'handler' => ['required', Rule::in(self::handlers())],
                'variables' => ['present', 'array'],
            ],
            self::INVOICE_ISSUED_HANDLER, self::PAYMENT_REMINDER_HANDLER => [
                'handler' => ['required', Rule::in(self::handlers())],
                'invoice_id' => ['required', 'integer', 'min:1'],
                'note' => ['nullable', 'string'],
            ],
            self::PAYMENT_RECEIPT_HANDLER => [
                'handler' => ['required', Rule::in(self::handlers())],
                'invoice_id' => ['required', 'integer', 'min:1'],
                'invoice_transaction_id' => ['required', 'integer', 'min:1'],
                'note' => ['nullable', 'string'],
            ],
            default => [],
        };

        if ($rules === []) {
            throw ValidationException::withMessages([
                'delivery' => __('app.communication.validation.delivery_not_retryable'),
            ]);
        }

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

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private function dispatch(CommunicationDelivery $delivery, array $payload): void
    {
        $deliveryId = (int) $delivery->getKey();
        $actorId = $delivery->actor_id;

        match ($payload['handler']) {
            self::COMMUNICATION_HANDLER => SendCommunicationEmail::dispatch(
                deliveryId: $deliveryId,
            )->afterCommit(),
            self::INVOICE_ISSUED_HANDLER => SendInvoiceIssuedEmail::dispatch(
                invoiceId: (int) $payload['invoice_id'],
                toEmail: $delivery->recipient,
                note: $payload['note'] ?? null,
                actorId: $actorId,
                deliveryId: $deliveryId,
            )->afterCommit(),
            self::PAYMENT_REMINDER_HANDLER => SendInvoicePaymentReminderEmail::dispatch(
                invoiceId: (int) $payload['invoice_id'],
                toEmail: $delivery->recipient,
                note: $payload['note'] ?? null,
                actorId: $actorId,
                deliveryId: $deliveryId,
            )->afterCommit(),
            self::PAYMENT_RECEIPT_HANDLER => SendInvoicePaymentReceiptEmail::dispatch(
                invoiceId: (int) $payload['invoice_id'],
                invoiceTransactionId: (int) $payload['invoice_transaction_id'],
                toEmail: $delivery->recipient,
                note: $payload['note'] ?? null,
                actorId: $actorId,
                deliveryId: $deliveryId,
            )->afterCommit(),
        };
    }

    /** @return list<string> */
    private static function handlers(): array
    {
        return [
            self::COMMUNICATION_HANDLER,
            self::INVOICE_ISSUED_HANDLER,
            self::PAYMENT_REMINDER_HANDLER,
            self::PAYMENT_RECEIPT_HANDLER,
        ];
    }
}
