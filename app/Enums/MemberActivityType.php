<?php

namespace App\Enums;

enum MemberActivityType: string
{
    public const LogName = 'members';

    case MemberCreated = 'member_created';
    case MemberUpdated = 'member_updated';
    case MemberStatusChanged = 'member_status_changed';
    case SubscriptionCreated = 'subscription_created';
    case SubscriptionRenewed = 'subscription_renewed';
    case SubscriptionStatusChanged = 'subscription_status_changed';
}
