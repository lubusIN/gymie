<?php

namespace App\Services\Api\Schemas;

use App\Enums\Status;
use App\Models\Enquiry;
use App\Models\User;
use App\Rules\ModelExists;
use App\Rules\ModelUnique;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Single source of truth for Enquiry API validation and serialization.
 */
final class EnquirySchema
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
            'searchable' => ['name', 'email', 'contact'],
            'sortable' => ['id', 'created_at', 'date'],
            'default_sort' => '-id',
            'status_column' => 'status',
            'includes' => ['followUps', 'member', 'nextFollowUp', 'user'],
            'filters' => [
                'user_id' => ['type' => 'exact', 'column' => 'user_id'],
                'status' => ['type' => 'exact', 'column' => 'status'],
                'date' => ['type' => 'date_range', 'column' => 'date'],
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
            'user_id' => ['prohibited'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', new ModelUnique(Enquiry::class, 'email')],
            'contact' => ['required', 'string', 'max:20'],
            'date' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female', 'other'])],
            'dob' => ['nullable', 'date'],
            'status' => ['prohibited'],
            'lost_reason' => ['prohibited'],
            'address' => ['nullable', 'string'],
            'country' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'interested_in' => ['nullable', 'array'],
            'interested_in.*' => ['string'],
            'source' => ['nullable', 'string', 'max:255'],
            'goal' => ['nullable', 'string', 'max:255'],
            'start_by' => ['nullable', 'date'],
            'follow_up' => ['sometimes', 'array'],
            'follow_up.method' => ['required_with:follow_up', 'string', 'max:50'],
            'follow_up.schedule_date' => ['required_with:follow_up', 'date'],
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function updateRules(int|string $enquiryId): array
    {
        return [
            'user_id' => ['sometimes', 'integer', new ModelExists(User::class)],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', new ModelUnique(Enquiry::class, 'email', $enquiryId)],
            'contact' => ['sometimes', 'string', 'max:20'],
            'date' => ['sometimes', 'nullable', 'date'],
            'gender' => ['sometimes', 'nullable', 'string', Rule::in(['male', 'female', 'other'])],
            'dob' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'string', Rule::in([
                Status::Lead->value,
                Status::Lost->value,
            ])],
            'lost_reason' => ['nullable', 'string', 'max:1000', 'required_if:status,lost'],
            'address' => ['sometimes', 'nullable', 'string'],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'state' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pincode' => ['sometimes', 'nullable', 'string', 'max:20'],
            'interested_in' => ['sometimes', 'nullable', 'array'],
            'interested_in.*' => ['string'],
            'source' => ['sometimes', 'nullable', 'string', 'max:255'],
            'goal' => ['sometimes', 'nullable', 'string', 'max:255'],
            'start_by' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resource(Enquiry $enquiry): array
    {
        return [
            'id' => (int) $enquiry->id,
            'user_id' => (int) $enquiry->user_id,
            'name' => (string) $enquiry->name,
            'email' => (string) $enquiry->email,
            'contact' => $enquiry->contact ? (string) $enquiry->contact : null,
            'date' => $enquiry->date?->toDateString(),
            'gender' => $enquiry->gender ? (string) $enquiry->gender : null,
            'dob' => $enquiry->dob?->toDateString(),
            'status' => Status::valueOf($enquiry->status),
            'lost_reason' => $enquiry->lost_reason ? (string) $enquiry->lost_reason : null,
            'converted_at' => $enquiry->converted_at?->toISOString(),
            'converted_member_id' => $enquiry->member?->id,
            'next_follow_up_at' => $enquiry->nextFollowUp?->schedule_date?->toDateString(),
            'address' => $enquiry->address ? (string) $enquiry->address : null,
            'country' => $enquiry->country ? (string) $enquiry->country : null,
            'state' => $enquiry->state ? (string) $enquiry->state : null,
            'city' => $enquiry->city ? (string) $enquiry->city : null,
            'pincode' => $enquiry->pincode ? (string) $enquiry->pincode : null,
            'interested_in' => is_array($enquiry->interested_in) ? $enquiry->interested_in : [],
            'source' => $enquiry->source ? (string) $enquiry->source : null,
            'goal' => $enquiry->goal ? (string) $enquiry->goal : null,
            'start_by' => $enquiry->start_by?->toDateString(),
            'created_at' => $enquiry->created_at?->toISOString(),
            'updated_at' => $enquiry->updated_at?->toISOString(),
            'deleted_at' => $enquiry->deleted_at?->toISOString(),
        ];
    }
}
