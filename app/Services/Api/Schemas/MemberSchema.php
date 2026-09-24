<?php

namespace App\Services\Api\Schemas;

use App\Enums\Status;
use App\Models\Member;
use App\Rules\ModelUnique;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Single source of truth for Member API validation and serialization.
 *
 * Keep this explicit (not auto-reflecting DB schema) so API contracts remain stable.
 */
final class MemberSchema
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
            'searchable' => ['code', 'name', 'email', 'contact'],
            'sortable' => ['id', 'created_at', 'name'],
            'default_sort' => '-id',
            'status_column' => 'status',
            'includes' => ['enquiry', 'subscriptions', 'subscriptions.plan', 'subscriptions.invoices'],
            'filters' => [
                'status' => ['type' => 'exact', 'column' => 'status'],
                'gender' => ['type' => 'exact', 'column' => 'gender'],
                'source' => ['type' => 'exact', 'column' => 'source'],
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
            'photo' => ['nullable', 'file', 'image', 'max:10240'],
            'code' => ['nullable', 'string', 'max:255', new ModelUnique(Member::class, 'code')],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', new ModelUnique(Member::class, 'email')],
            'contact' => ['required', 'string', 'max:20'],
            'emergency_contact' => ['nullable', 'string', 'max:20'],
            'health_issue' => ['nullable', 'string', 'max:500'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female', 'other'])],
            'dob' => ['nullable', 'date'],
            'address' => ['nullable', 'string'],
            'country' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'source' => ['nullable', 'string', 'max:255'],
            'goal' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function updateRules(int|string $memberId): array
    {
        return [
            'photo' => ['sometimes', 'nullable', 'file', 'image', 'max:10240'],
            'code' => ['sometimes', 'nullable', 'string', 'max:255', new ModelUnique(Member::class, 'code', $memberId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', new ModelUnique(Member::class, 'email', $memberId)],
            'contact' => ['sometimes', 'string', 'max:20'],
            'emergency_contact' => ['sometimes', 'nullable', 'string', 'max:20'],
            'health_issue' => ['sometimes', 'nullable', 'string', 'max:500'],
            'gender' => ['sometimes', 'nullable', 'string', Rule::in(['male', 'female', 'other'])],
            'dob' => ['sometimes', 'nullable', 'date'],
            'address' => ['sometimes', 'nullable', 'string'],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'state' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pincode' => ['sometimes', 'nullable', 'string', 'max:20'],
            'source' => ['sometimes', 'nullable', 'string', 'max:255'],
            'goal' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resource(Member $member): array
    {
        return [
            'id' => (int) $member->id,
            'enquiry_id' => $member->enquiry_id,
            'code' => $member->code ? (string) $member->code : null,
            'name' => (string) $member->name,
            'email' => (string) $member->email,
            'contact' => $member->contact ? (string) $member->contact : null,
            'emergency_contact' => $member->emergency_contact ? (string) $member->emergency_contact : null,
            'health_issue' => $member->health_issue ? (string) $member->health_issue : null,
            'gender' => $member->gender ? (string) $member->gender : null,
            'dob' => $member->dob?->toDateString(),
            'photo' => $member->photo ? (string) $member->photo : null,
            'photo_url' => $member->photo ? Storage::disk('public')->url((string) $member->photo) : null,
            'address' => $member->address ? (string) $member->address : null,
            'country' => $member->country ? (string) $member->country : null,
            'state' => $member->state ? (string) $member->state : null,
            'city' => $member->city ? (string) $member->city : null,
            'pincode' => $member->pincode ? (string) $member->pincode : null,
            'source' => $member->source ? (string) $member->source : null,
            'goal' => $member->goal ? (string) $member->goal : null,
            'status' => Status::valueOf($member->status),
            'created_at' => $member->created_at?->toISOString(),
            'updated_at' => $member->updated_at?->toISOString(),
            'deleted_at' => $member->deleted_at?->toISOString(),
        ];
    }
}
