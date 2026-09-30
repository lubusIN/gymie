<?php

namespace App\Enums;

enum SubscriptionActivityType: string
{
    public const LogName = 'subscriptions';

    case SubscriptionCreated = 'subscription_created';
    case SubscriptionRenewed = 'subscription_renewed';
    case SubscriptionStatusChanged = 'subscription_status_changed';
    case InvoiceCreated = 'invoice_created';
    case InvoiceStatusChanged = 'invoice_status_changed';
    case PaymentRecorded = 'payment_recorded';
    case PaymentReminderQueued = 'payment_reminder_queued';
    case RefundRecorded = 'refund_recorded';
}
