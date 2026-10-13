<?php

namespace App\Services\Api;

use App\Services\Api\Schemas\EnquirySchema;
use App\Services\Api\Schemas\ExpenseSchema;
use App\Services\Api\Schemas\FollowUpSchema;
use App\Services\Api\Schemas\InvoiceSchema;
use App\Services\Api\Schemas\MemberSchema;
use App\Services\Api\Schemas\PlanSchema;
use App\Services\Api\Schemas\ServiceSchema;
use App\Services\Api\Schemas\SubscriptionSchema;
use App\Services\Api\Schemas\UserSchema;
use InvalidArgumentException;

/**
 * Central, allowlisted query rules for API index endpoints.
 *
 * This is intentionally explicit (not "dynamic") so we can:
 * - avoid accidental exposure of sensitive columns via search/sort
 * - keep behavior consistent across controllers
 * - reduce duplication and make future package extraction easier
 */
final class ResourceQueryRules
{
    /**
     * @var array<string, class-string>
     */
    private const SCHEMAS = [
        'members' => MemberSchema::class,
        'users' => UserSchema::class,
        'services' => ServiceSchema::class,
        'plans' => PlanSchema::class,
        'subscriptions' => SubscriptionSchema::class,
        'invoices' => InvoiceSchema::class,
        'expenses' => ExpenseSchema::class,
        'enquiries' => EnquirySchema::class,
        'follow-ups' => FollowUpSchema::class,
    ];

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
    public static function forResource(string $resourceKey): array
    {
        if (! array_key_exists($resourceKey, self::SCHEMAS)) {
            throw new InvalidArgumentException("Unknown API resource key [{$resourceKey}].");
        }

        $schema = self::SCHEMAS[$resourceKey];

        if (! method_exists($schema, 'queryRules')) {
            throw new InvalidArgumentException("API schema [{$schema}] must define a static queryRules() method.");
        }

        /** @var array{
         *   searchable: list<string>,
         *   sortable: list<string>,
         *   default_sort: string,
         *   status_column: string|null,
         *   includes: list<string>,
         *   filters: array<string, array{type: string, column: string}>
         * } $rules
         */
        $rules = $schema::queryRules();

        foreach (['searchable', 'sortable', 'default_sort', 'status_column', 'includes', 'filters'] as $key) {
            if (! array_key_exists($key, $rules)) {
                throw new InvalidArgumentException("API schema [{$schema}] queryRules() is missing required key [{$key}].");
            }
        }

        return $rules;
    }
}
