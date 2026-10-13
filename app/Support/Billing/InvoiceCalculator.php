<?php

namespace App\Support\Billing;

use App\Enums\Status;

/**
 * Keep persisted and displayed invoice calculations consistent.
 *
 * This calculator intentionally matches the current UI behavior:
 * - Values are rounded to whole currency units for on-screen summaries.
 * - Discount amount is clamped to the fee (cannot exceed it).
 * - Paid amount is clamped to the computed total.
 */
final class InvoiceCalculator
{
    /**
     * Calculate summary values (fee/tax/total/paid/due) for a single invoice.
     *
     * @return array{
     *   fee: float,
     *   tax: float,
     *   discount_amount: float,
     *   total: float,
     *   paid: float,
     *   due: float,
     * }
     */
    public static function summary(
        float $fee,
        float $taxRatePercent,
        float $discountAmount,
        float $paidAmount,
    ): array {
        $fee = max(round($fee), 0.0);
        $taxRatePercent = max($taxRatePercent, 0.0);

        $tax = round(($fee * $taxRatePercent) / 100);

        $discountAmount = min(max($discountAmount, 0.0), $fee);
        $discountAmount = round($discountAmount);

        $total = round(max($fee + $tax - $discountAmount, 0.0));

        $paidAmount = min(max($paidAmount, 0.0), $total);
        $paidAmount = round($paidAmount);

        $due = round(max($total - $paidAmount, 0.0));

        return [
            'fee' => (float) $fee,
            'tax' => (float) $tax,
            'discount_amount' => (float) $discountAmount,
            'total' => (float) $total,
            'paid' => (float) $paidAmount,
            'due' => (float) $due,
        ];
    }

    /**
     * Reconcile an invoice balance against its payment and refund ledger.
     *
     * @return array{paid: float, due: float, status: string}
     */
    public static function ledger(
        float $totalAmount,
        float $paymentsTotal,
        float $refundsTotal,
        ?string $currentStatus,
        bool $isPastDue,
    ): array {
        $totalAmount = max($totalAmount, 0.0);
        $paymentsTotal = max($paymentsTotal, 0.0);
        $refundsTotal = min(max($refundsTotal, 0.0), $paymentsTotal);
        $paidAmount = min(max($paymentsTotal - $refundsTotal, 0.0), $totalAmount);
        $dueAmount = max($totalAmount - $paidAmount, 0.0);
        $status = $currentStatus ?? Status::Issued->value;

        if ($status === Status::Cancelled->value) {
            $dueAmount = 0.0;
        } elseif ($refundsTotal > 0) {
            $status = Status::Refund->value;
            $dueAmount = 0.0;
        } elseif ($dueAmount <= 0 && $paidAmount > 0) {
            $status = Status::Paid->value;
            $dueAmount = 0.0;
        } elseif ($paidAmount > 0) {
            $status = Status::Partial->value;
        } else {
            $status = Status::Issued->value;
        }

        if ($dueAmount > 0 && $isPastDue) {
            $status = Status::Overdue->value;
        }

        return [
            'paid' => $paidAmount,
            'due' => $dueAmount,
            'status' => $status,
        ];
    }
}
