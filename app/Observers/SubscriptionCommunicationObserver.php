<?php

namespace App\Observers;

use App\Enums\CommunicationTemplateKey;
use App\Enums\Status;
use App\Models\Subscription;
use App\Services\Communication\CommunicationAutomationService;
use App\Services\Communication\CommunicationService;
use Illuminate\Support\Facades\Log;

class SubscriptionCommunicationObserver
{
    public function __construct(
        private readonly CommunicationAutomationService $automationService,
        private readonly CommunicationService $communicationService,
    ) {}

    /** Confirm a membership renewal when the replacement subscription is created. */
    public function created(Subscription $subscription): void
    {
        if (
            $subscription->renewed_from_subscription_id === null
            || ! $this->automationService->shouldSend(CommunicationTemplateKey::MembershipRenewed)
        ) {
            return;
        }

        $this->queueMembershipMessage($subscription, CommunicationTemplateKey::MembershipRenewed);
    }

    /**
     * Handle the Subscription "updated" event.
     */
    public function updated(Subscription $subscription): void
    {
        if (! $subscription->wasChanged('status')) {
            return;
        }

        $event = match ($subscription->status) {
            Status::Expiring => CommunicationTemplateKey::MembershipExpiring,
            Status::Expired => CommunicationTemplateKey::MembershipExpired,
            default => null,
        };

        if ($event === null || ! $this->automationService->shouldSend($event)) {
            return;
        }

        $this->queueMembershipMessage($subscription, $event);
    }

    /** Queue a membership lifecycle message using a consistent variable set. */
    private function queueMembershipMessage(
        Subscription $subscription,
        CommunicationTemplateKey $event,
    ): void {

        $subscription->loadMissing(['member', 'plan']);
        $member = $subscription->member;

        if (! filled($member?->email)) {
            Log::info('Skipping membership expiry email: member email missing.', [
                'subscription_id' => $subscription->getKey(),
            ]);

            return;
        }

        $this->communicationService->queue(
            templateKey: $event,
            recipient: (string) $member->email,
            variables: [
                'member_name' => $member->name,
                'member_code' => $member->code,
                'plan_name' => $subscription->plan?->name ?? '',
                'start_date' => $subscription->start_date?->format('M j, Y') ?? '',
                'end_date' => $subscription->end_date?->format('M j, Y') ?? '',
            ],
            related: $subscription,
        );
    }
}
