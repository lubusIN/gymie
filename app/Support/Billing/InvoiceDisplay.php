<?php

namespace App\Support\Billing;

use App\Enums\Status;
use App\Models\Invoice;
use App\Support\AppConfig;

final class InvoiceDisplay
{
    private function __construct() {}

    /** Return a localized age only when an invoice is currently overdue. */
    public static function overdueAge(Invoice $invoice): ?string
    {
        $dueDate = $invoice->due_date?->copy()->startOfDay();
        $today = now(AppConfig::timezone())->startOfDay();

        if ($invoice->status !== Status::Overdue || $dueDate === null || ! $dueDate->isBefore($today)) {
            return null;
        }

        $daysOverdue = (int) $dueDate->diffInDays($today);

        return trans_choice('app.help.days_overdue', $daysOverdue, ['count' => $daysOverdue]);
    }
}
