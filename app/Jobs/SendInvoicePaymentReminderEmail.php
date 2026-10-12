<?php

namespace App\Jobs;

use App\Services\Communication\CommunicationDeliveryTracker;
use App\Services\Email\InvoiceEmailService;
use App\Support\Invoices\InvoiceDocumentNotRenderable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Send a payment reminder for an invoice with an outstanding balance.
 */
class SendInvoicePaymentReminderEmail implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    /**
     * Prevent repeated reminders to the same recipient within the retry window.
     */
    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly int $invoiceId,
        public readonly string $toEmail,
        public readonly ?string $note = null,
        public readonly ?int $actorId = null,
        public readonly ?int $deliveryId = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(InvoiceEmailService $service): void
    {
        try {
            $service->sendPaymentReminderEmail(
                invoiceId: $this->invoiceId,
                toEmail: $this->toEmail,
                note: $this->note,
                deliveryId: $this->deliveryId,
            );
        } catch (InvoiceDocumentNotRenderable $exception) {
            app(CommunicationDeliveryTracker::class)->failed($this->deliveryId, $exception);
            Log::warning('Skipping payment reminder email: missing required invoice data.', [
                'invoice_id' => $this->invoiceId,
                'missing' => $exception->viewData['missing'],
            ]);
        }
    }

    /**
     * Identify one payment reminder delivery to one recipient.
     */
    public function uniqueId(): string
    {
        return "{$this->invoiceId}:".Str::lower(trim($this->toEmail));
    }

    public function failed(?Throwable $exception): void
    {
        app(CommunicationDeliveryTracker::class)->failed($this->deliveryId, $exception);

        Log::error('Invoice payment reminder email job failed permanently.', [
            'invoice_id' => $this->invoiceId,
            'actor_id' => $this->actorId,
            'exception' => $exception,
        ]);
    }
}
