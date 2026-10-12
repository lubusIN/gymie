<?php

namespace App\Jobs;

use App\Services\Communication\CommunicationDeliveryTracker;
use App\Services\Communication\CommunicationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendCommunicationEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly int $deliveryId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CommunicationService $service): void
    {
        $service->send(
            deliveryId: $this->deliveryId,
        );
    }

    /** Record a permanent queue failure without exposing message content. */
    public function failed(?Throwable $exception): void
    {
        app(CommunicationDeliveryTracker::class)->failed($this->deliveryId, $exception);

        Log::error('Communication email job failed permanently.', [
            'delivery_id' => $this->deliveryId,
            'exception' => $exception,
        ]);
    }
}
