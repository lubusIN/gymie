<?php

namespace App\Services\Api\Schemas;

use App\Enums\Status;
use App\Http\Resources\V1\InvoiceResource;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use App\Rules\ModelExists;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Single source of truth for Subscription API validation and serialization.
 */
final class SubscriptionSchema
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
            'searchable' => [],
            'sortable' => ['id', 'created_at', 'start_date', 'end_date'],
            'default_sort' => '-id',
            'status_column' => 'status',
            'includes' => ['member', 'plan', 'invoices'],
            'filters' => [
                'member_id' => ['type' => 'exact', 'column' => 'member_id'],
                'plan_id' => ['type' => 'exact', 'column' => 'plan_id'],
                'status' => ['type' => 'exact', 'column' => 'status'],
                'start_date' => ['type' => 'date_range', 'column' => 'start_date'],
                'end_date' => ['type' => 'date_range', 'column' => 'end_date'],
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
            'member_id' => ['required', 'integer', new ModelExists(Member::class)],
            'plan_id' => ['required', 'integer', new ModelExists(Plan::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string'],
            'invoice' => ['sometimes', 'array'],
            ...self::invoiceRules('invoice.'),
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function updateRules(): array
    {
        return [
            'member_id' => ['sometimes', 'integer', new ModelExists(Member::class)],
            'plan_id' => ['sometimes', 'integer', new ModelExists(Plan::class)],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'status' => ['sometimes', 'string'],
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function renewRules(): array
    {
        return [
            'plan_id' => ['required', 'integer', new ModelExists(Plan::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'invoice' => ['sometimes', 'array'],
            ...self::invoiceRules('invoice.'),
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    private static function invoiceRules(string $prefix): array
    {
        return [
            "{$prefix}number" => ['nullable', 'string', 'max:255'],
            "{$prefix}date" => ['nullable', 'date'],
            "{$prefix}due_date" => ['nullable', 'date'],
            "{$prefix}payment_method" => ['nullable', 'string', 'max:50'],
            "{$prefix}discount" => ['nullable', 'numeric', 'between:0,100'],
            "{$prefix}discount_amount" => ['nullable', 'numeric', 'min:0'],
            "{$prefix}discount_note" => ['nullable', 'string'],
            "{$prefix}paid_amount" => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resource(Subscription $subscription): array
    {
        $payload = [
            'id' => (int) $subscription->id,
            'renewed_from_subscription_id' => $subscription->renewed_from_subscription_id ? (int) $subscription->renewed_from_subscription_id : null,
            'member_id' => (int) $subscription->member_id,
            'plan_id' => (int) $subscription->plan_id,
            'start_date' => $subscription->start_date?->toDateString(),
            'end_date' => $subscription->end_date?->toDateString(),
            'status' => Status::valueOf($subscription->status),
            'created_at' => $subscription->created_at?->toISOString(),
            'updated_at' => $subscription->updated_at?->toISOString(),
            'deleted_at' => $subscription->deleted_at?->toISOString(),
        ];

        if ($subscription->relationLoaded('member') && $subscription->member) {
            $payload['member'] = [
                'id' => (int) $subscription->member->id,
                'code' => $subscription->member->code ? (string) $subscription->member->code : null,
                'name' => (string) $subscription->member->name,
            ];
        }

        if ($subscription->relationLoaded('plan') && $subscription->plan) {
            $payload['plan'] = [
                'id' => (int) $subscription->plan->id,
                'code' => $subscription->plan->code ? (string) $subscription->plan->code : null,
                'name' => (string) $subscription->plan->name,
                'amount' => (float) ($subscription->plan->amount ?? 0),
                'days' => (int) ($subscription->plan->days ?? 0),
            ];
        }

        if ($subscription->relationLoaded('invoices')) {
            $payload['invoices'] = InvoiceResource::collection($subscription->invoices);
        }

        return $payload;
    }
}
