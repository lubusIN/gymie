<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceTransactionService
{
    /**
     * Record an invoice transaction while serializing balance updates.
     *
     * @param  array{
     *     type: string,
     *     amount: float|int|string,
     *     occurred_at: mixed,
     *     payment_method?: string|null,
     *     note?: string|null,
     *     reference_id?: string|null,
     *     created_by?: int|null
     * }  $attributes
     */
    public function record(Invoice $invoice, array $attributes): InvoiceTransaction
    {
        return DB::transaction(function () use ($invoice, $attributes): InvoiceTransaction {
            $lockedInvoice = Invoice::query()
                ->lockForUpdate()
                ->findOrFail($invoice->getKey());

            $attributes['amount'] = $this->validatedAmount($lockedInvoice, $attributes);

            return $lockedInvoice->transactions()
                ->create($attributes)
                ->refresh();
        }, 3);
    }

    /**
     * Refund the current paid balance, returning null when it is no longer refundable.
     *
     * @param  array{
     *     occurred_at: mixed,
     *     note?: string|null,
     *     created_by?: int|null
     * }  $attributes
     */
    public function refundPaidBalance(Invoice $invoice, array $attributes): ?InvoiceTransaction
    {
        return DB::transaction(function () use ($invoice, $attributes): ?InvoiceTransaction {
            $lockedInvoice = Invoice::query()
                ->lockForUpdate()
                ->findOrFail($invoice->getKey());

            $amount = max((float) ($lockedInvoice->paid_amount ?? 0), 0);

            if (
                $amount <= 0
                || in_array($lockedInvoice->status?->value, ['refund', 'cancelled'], true)
            ) {
                return null;
            }

            return $lockedInvoice->transactions()
                ->create([
                    ...$attributes,
                    'type' => 'refund',
                    'amount' => $amount,
                ])
                ->refresh();
        }, 3);
    }

    /**
     * Delete an invoice transaction while serializing balance updates.
     */
    public function delete(Invoice $invoice, InvoiceTransaction $transaction): void
    {
        DB::transaction(function () use ($invoice, $transaction): void {
            $lockedInvoice = Invoice::query()
                ->lockForUpdate()
                ->findOrFail($invoice->getKey());

            $lockedTransaction = $lockedInvoice->transactions()
                ->findOrFail($transaction->getKey());

            $lockedTransaction->delete();
        }, 3);
    }

    /**
     * @param  array{type: string, amount: float|int|string}  $attributes
     */
    private function validatedAmount(Invoice $invoice, array $attributes): float
    {
        $amount = (float) $attributes['amount'];
        $available = $attributes['type'] === 'refund'
            ? (float) ($invoice->paid_amount ?? 0)
            : (float) ($invoice->due_amount ?? 0);

        if ($amount <= 0 || $amount > $available) {
            throw ValidationException::withMessages([
                'amount' => __('validation.between.numeric', [
                    'attribute' => 'amount',
                    'min' => 0.01,
                    'max' => max($available, 0),
                ]),
            ]);
        }

        return $amount;
    }
}
