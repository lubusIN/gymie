<?php

namespace App\Services\Api\Schemas;

use App\Enums\Status;
use App\Models\Enquiry;
use App\Models\FollowUp;
use App\Models\User;
use App\Rules\ModelExists;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Single source of truth for FollowUp API validation and serialization.
 */
final class FollowUpSchema
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
            'sortable' => ['id', 'created_at', 'schedule_date'],
            'default_sort' => '-id',
            'status_column' => 'status',
            'includes' => ['enquiry', 'user'],
            'filters' => [
                'enquiry_id' => ['type' => 'exact', 'column' => 'enquiry_id'],
                'user_id' => ['type' => 'exact', 'column' => 'user_id'],
                'status' => ['type' => 'exact', 'column' => 'status'],
                'schedule_date' => ['type' => 'date_range', 'column' => 'schedule_date'],
                'created_at' => ['type' => 'datetime_range', 'column' => 'created_at'],
            ],
        ];
    }

    /**
     * Rules for creating a follow-up under an enquiry (enquiry_id comes from route).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function nestedStoreRules(): array
    {
        return [
            'user_id' => ['prohibited', 'integer'],
            'schedule_date' => ['required', 'date'],
            'method' => ['required', 'string', Rule::in(['call', 'email', 'in_person', 'whatsapp', 'other'])],
            'outcome' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in([Status::Pending->value, Status::Done->value])],
        ];
    }

    /**
     * Rules for creating a follow-up directly.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function storeRules(): array
    {
        return [
            'enquiry_id' => ['required', 'integer', new ModelExists(Enquiry::class)],
            'user_id' => ['prohibited', 'integer'],
            'schedule_date' => ['required', 'date'],
            'method' => ['required', 'string', Rule::in(['call', 'email', 'in_person', 'whatsapp', 'other'])],
            'outcome' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in([Status::Pending->value, Status::Done->value])],
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function updateRules(): array
    {
        return [
            'enquiry_id' => ['sometimes', 'integer', new ModelExists(Enquiry::class)],
            'user_id' => ['sometimes', 'integer', new ModelExists(User::class)],
            'schedule_date' => ['sometimes', 'date'],
            'method' => ['sometimes', 'string', Rule::in(['call', 'email', 'in_person', 'whatsapp', 'other'])],
            'outcome' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in([Status::Pending->value, Status::Done->value])],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resource(FollowUp $followUp): array
    {
        return [
            'id' => (int) $followUp->id,
            'enquiry_id' => (int) $followUp->enquiry_id,
            'user_id' => (int) $followUp->user_id,
            'schedule_date' => $followUp->schedule_date?->toDateString(),
            'method' => $followUp->method ? (string) $followUp->method : null,
            'outcome' => $followUp->outcome ? (string) $followUp->outcome : null,
            'status' => Status::valueOf($followUp->status),
            'created_at' => $followUp->created_at?->toISOString(),
            'updated_at' => $followUp->updated_at?->toISOString(),
            'deleted_at' => $followUp->deleted_at?->toISOString(),
        ];
    }
}
