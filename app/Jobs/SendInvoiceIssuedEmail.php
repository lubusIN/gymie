<?php

namespace App\Jobs;

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
 * Send the "invoice issued" email (queued).
 */
class SendInvoiceIssuedEmail implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    public int $timeout = 60;

    /**
     * Keep duplicate delivery requests locked for longer than the retry window.
     */
    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $invoiceId,
        public readonly string $toEmail,
        public readonly ?string $note = null,
        public readonly ?int $actorId = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(InvoiceEmailService $service): void
    {
        try {
            $service->sendInvoiceIssuedEmail(
                invoiceId: $this->invoiceId,
                toEmail: $this->toEmail,
                note: $this->note,
            );
        } catch (InvoiceDocumentNotRenderable $exception) {
            Log::warning('Skipping invoice email: missing required invoice data.', [
                'invoice_id' => $this->invoiceId,
                'missing' => $exception->viewData['missing'],
            ]);
        }
    }

    /**
     * Identify one invoice delivery to one recipient.
     */
    public function uniqueId(): string
    {
        return "{$this->invoiceId}:".Str::lower(trim($this->toEmail));
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Invoice issued email job failed permanently.', [
            'invoice_id' => $this->invoiceId,
            'actor_id' => $this->actorId,
            'exception' => $exception,
        ]);
    }
}
