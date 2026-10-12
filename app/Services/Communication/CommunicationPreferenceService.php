<?php

namespace App\Services\Communication;

use App\Models\Invoice;
use App\Models\Member;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Model;

final class CommunicationPreferenceService
{
    /** Return the reason email delivery must be skipped, when one applies. */
    public function emailOptOutReason(?Model $related): ?string
    {
        $member = $this->memberFor($related);

        if ($member === null || $member->allowsEmailCommunication()) {
            return null;
        }

        return __('app.communication.member_email_opted_out');
    }

    /** Resolve the member represented by a member-related communication record. */
    private function memberFor(?Model $related): ?Member
    {
        if ($related instanceof Member) {
            return $related;
        }

        if ($related instanceof Subscription) {
            $related->loadMissing('member');

            return $related->member;
        }

        if ($related instanceof Invoice) {
            $related->loadMissing('subscription.member');

            return $related->subscription?->member;
        }

        return null;
    }
}
