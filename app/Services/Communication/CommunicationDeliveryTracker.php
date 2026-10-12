<?php

namespace App\Services\Communication;

use App\Enums\CommunicationDeliveryStatus;
use App\Enums\CommunicationTemplateKey;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

final class CommunicationDeliveryTracker
{
    public function __construct(
        private readonly CommunicationAutomationService $automationService,
        private readonly CommunicationPreferenceService $preferenceService,
    ) {}

    /**
     * Record a queued delivery before the job is dispatched.
     *
     * @param  array<string, mixed>  $retryPayload
     */
    public function queued(
        CommunicationTemplateKey|string $key,
        string $recipient,
        ?Model $related = null,
        ?int $actorId = null,
        array $retryPayload = [],
        bool $isTest = false,
    ): CommunicationDelivery {
        $template = $key instanceof CommunicationTemplateKey
            ? $this->automationService->templateFor($key)
            : CommunicationTemplate::query()->where('key', $key)->firstOrFail();
        $optOutReason = $template->channel === 'email'
            ? $this->preferenceService->emailOptOutReason($related)
            : null;

        return CommunicationDelivery::query()->create([
            'communication_template_id' => $template->getKey(),
            'actor_id' => $actorId,
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
            'channel' => $template->channel,
            'recipient' => Str::lower(trim($recipient)),
            'status' => $optOutReason === null
                ? CommunicationDeliveryStatus::Queued
                : CommunicationDeliveryStatus::Skipped,
            'is_test' => $isTest,
            'queued_at' => now(),
            'failure_reason' => $optOutReason,
            'retry_payload' => $retryPayload === [] ? null : $retryPayload,
        ]);
    }

    /**
     * Mark a delivery as successfully sent.
     */
    public function sent(?int $deliveryId, string $subject): void
    {
        $this->update($deliveryId, [
            'subject' => $subject,
            'status' => CommunicationDeliveryStatus::Sent,
            'sent_at' => now(),
            'failed_at' => null,
            'failure_reason' => null,
        ]);
    }

    /**
     * Mark a delivery as intentionally skipped.
     */
    public function skipped(?int $deliveryId, string $reason): void
    {
        $this->update($deliveryId, [
            'status' => CommunicationDeliveryStatus::Skipped,
            'failure_reason' => Str::limit($reason, 500),
        ]);
    }

    /**
     * Mark a delivery as failed without storing stack traces or sensitive context.
     */
    public function failed(?int $deliveryId, Throwable|string|null $reason): void
    {
        $message = $reason instanceof Throwable ? $reason->getMessage() : $reason;

        $this->update($deliveryId, [
            'status' => CommunicationDeliveryStatus::Failed,
            'failed_at' => now(),
            'failure_reason' => Str::limit(trim((string) $message), 500),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function update(?int $deliveryId, array $attributes): void
    {
        if ($deliveryId === null) {
            return;
        }

        CommunicationDelivery::query()->whereKey($deliveryId)->update($attributes);
    }
}
