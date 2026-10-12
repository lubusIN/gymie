<?php

namespace App\Observers;

use App\Enums\CommunicationTemplateKey;
use App\Models\FollowUp;
use App\Services\Communication\CommunicationAutomationService;
use App\Services\Communication\CommunicationService;
use Illuminate\Support\Facades\Log;

class FollowUpCommunicationObserver
{
    public function __construct(
        private readonly CommunicationAutomationService $automationService,
        private readonly CommunicationService $communicationService,
    ) {}

    /**
     * Handle the FollowUp "created" event.
     */
    public function created(FollowUp $followUp): void
    {
        if (
            $followUp->method !== 'email'
            || ! $this->automationService->shouldSend(CommunicationTemplateKey::EnquiryFollowUp)
        ) {
            return;
        }

        $followUp->loadMissing(['enquiry.user']);
        $enquiry = $followUp->enquiry;

        if (! filled($enquiry?->email)) {
            Log::info('Skipping enquiry follow-up email: enquiry email missing.', [
                'follow_up_id' => $followUp->getKey(),
            ]);

            return;
        }

        $interests = is_array($enquiry->interested_in) ? $enquiry->interested_in : [];

        $this->communicationService->queue(
            templateKey: CommunicationTemplateKey::EnquiryFollowUp,
            recipient: (string) $enquiry->email,
            variables: [
                'enquiry_name' => $enquiry->name,
                'interested_in' => implode(', ', $interests),
                'follow_up_date' => $followUp->schedule_date?->format('M j, Y') ?? '',
                'lead_owner' => $enquiry->user?->name ?? '',
            ],
            related: $followUp,
            actorId: is_numeric(auth()->id()) ? (int) auth()->id() : null,
        );
    }
}
