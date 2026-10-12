<?php

namespace App\Observers;

use App\Enums\CommunicationTemplateKey;
use App\Models\Member;
use App\Services\Communication\CommunicationAutomationService;
use App\Services\Communication\CommunicationService;
use Illuminate\Support\Facades\Log;

final class MemberCommunicationObserver
{
    public function __construct(
        private readonly CommunicationAutomationService $automationService,
        private readonly CommunicationService $communicationService,
    ) {}

    /** Queue a welcome email after a member has been created. */
    public function created(Member $member): void
    {
        if (! $this->automationService->shouldSend(CommunicationTemplateKey::MemberWelcome)) {
            return;
        }

        if (! filled($member->email)) {
            Log::info('Skipping member welcome email: member email missing.', [
                'member_id' => $member->getKey(),
            ]);

            return;
        }

        $this->communicationService->queue(
            templateKey: CommunicationTemplateKey::MemberWelcome,
            recipient: (string) $member->email,
            variables: [
                'member_name' => $member->name,
                'member_code' => $member->code,
            ],
            related: $member,
            actorId: is_numeric(auth()->id()) ? (int) auth()->id() : null,
        );
    }
}
