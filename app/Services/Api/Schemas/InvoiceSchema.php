<?php

namespace App\Services\Api\Schemas;

use App\Enums\Status;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Rules\ModelExists;
use App\Rules\ModelUnique;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Single source of truth for Invoice API validation and serialization.
 */
final class InvoiceSchema
{
    private function __construct() {}

    /**
     * @return array{
     *   searchable: list<string>,
     *   sortable: list<string>,
     *   default_sort: string,
     *   status_column: string|null,
     *   includes: list<string>,
     *   filters: array<string, array{type: string, column: string}>
     * }
     */
    public static function queryRules(): array
    {
        return [
            'searchable' => ['number', 'discount_note'],
            'sortable' => ['id', 'created_at', 'date', 'due_date'],
            'default_sort' => '-id',
            'status_column' => 'status',
            'includes' => ['subscription', 'subscription.member', 'subscription.plan'],
            'filters' => [
                'subscription_id' => ['type' => 'exact', 'column' => 'subscription_id'],
                'status' => ['type' => 'exact', 'column' => 'status'],
                'payment_method' => ['type' => 'exact', 'column' => 'payment_method'],
                'date' => ['type' => 'date_range', 'column' => 'date'],
                'due_date' => ['type' => 'date_range', 'column' => 'due_date'],
                'created_at' => ['type' => 'datetime_range', 'column' => 'created_at'],
            ],
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function storeRules(): array
    {
        return [
            'subscription_id' => ['required', 'integer', new ModelExists(Subscription::class)],
            'number' => ['nullable', 'string', 'max:255', new ModelUnique(Invoice::class, 'number')],
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string'],
            'discount' => ['nullable', 'numeric', 'between:0,100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_note' => ['nullable', 'string'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'subscription_fee' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function updateRules(int|string $invoiceId): array
    {
        return [
            'number' => ['sometimes', 'nullable', 'string', 'max:255', new ModelUnique(Invoice::class, 'number', $invoiceId)],
            'date' => ['sometimes', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'payment_method' => ['sometimes', 'nullable', 'string', 'max:50'],
            'status' => ['sometimes', 'nullable', 'string'],
            'discount' => ['sometimes', 'nullable', 'numeric', 'between:0,100'],
            'discount_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'discount_note' => ['sometimes', 'nullable', 'string'],
            'paid_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            // subscription_fee is intentionally excluded from update rules.
            // It is set once at invoice creation from the subscription plan and
            // must never be modified afterward. Allowing it here would let any
            // user with Update:Invoice permission zero-out a member's outstanding
            // balance via syncFromTransactions() without recording a payment.
            // The Filament admin panel enforces the same constraint with readOnly()
            // + disabled() on the invoice edit form.
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resource(Invoice $invoice): array
    {
        $payload = [
            'id' => (int) $invoice->id,
            'number' => $invoice->number ? (string) $invoice->number : null,
            'subscription_id' => (int) $invoice->subscription_id,
            'date' => $invoice->date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'payment_method' => $invoice->payment_method ? (string) $invoice->payment_method : null,
            'status' => Status::valueOf($invoice->status),
            'tax' => (float) ($invoice->tax ?? 0),
            'discount' => $invoice->discount !== null ? (float) $invoice->discount : null,
            'discount_amount' => (float) ($invoice->discount_amount ?? 0),
            'discount_note' => $invoice->discount_note ? (string) $invoice->discount_note : null,
            'subscription_fee' => (float) ($invoice->subscription_fee ?? 0),
            'total_amount' => (float) ($invoice->total_amount ?? 0),
            'paid_amount' => (float) ($invoice->paid_amount ?? 0),
            'due_amount' => (float) ($invoice->due_amount ?? 0),
            'created_at' => $invoice->created_at?->toISOString(),
            'updated_at' => $invoice->updated_at?->toISOString(),
            'deleted_at' => $invoice->deleted_at?->toISOString(),
        ];

        if ($invoice->relationLoaded('subscription') && $invoice->subscription) {
            $subscription = $invoice->subscription;

            $subscriptionPayload = [
                'id' => (int) $subscription->id,
                'member' => null,
                'plan' => null,
            ];

            if ($subscription->relationLoaded('member') && $subscription->member) {
                $subscriptionPayload['member'] = [
                    'id' => (int) $subscription->member->id,
                    'code' => $subscription->member->code ? (string) $subscription->member->code : null,
                    'name' => (string) $subscription->member->name,
                ];
            }

            if ($subscription->relationLoaded('plan') && $subscription->plan) {
                $subscriptionPayload['plan'] = [
                    'id' => (int) $subscription->plan->id,
                    'code' => $subscription->plan->code ? (string) $subscription->plan->code : null,
                    'name' => (string) $subscription->plan->name,
                    'amount' => (float) ($subscription->plan->amount ?? 0),
                    'days' => (int) ($subscription->plan->days ?? 0),
                ];
            }

            $payload['subscription'] = $subscriptionPayload;
        }

        return $payload;
    }
}
