<?php

namespace App\Services\Api\Schemas;

use App\Enums\Status;
use App\Models\User;
use App\Rules\ModelExists;
use App\Rules\ModelUnique;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Single source of truth for User API validation and serialization.
 */
final class UserSchema
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
            'sortable' => ['id', 'created_at', 'name'],
            'default_sort' => '-id',
            'status_column' => 'status',
            'includes' => ['roles', 'roles.permissions'],
            'filters' => [
                'status' => ['type' => 'exact', 'column' => 'status'],
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', new ModelUnique(User::class, 'email')],
            'contact' => ['nullable', 'string', 'max:20'],
            'dob' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female', 'other'])],
            'address' => ['nullable', 'string'],
            'country' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', new ModelExists(Role::class)],
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function updateRules(int|string $userId): array
    {
        return [
            'photo' => ['sometimes', 'nullable', 'file', 'image', 'max:10240'],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', new ModelUnique(User::class, 'email', $userId)],
            'contact' => ['sometimes', 'nullable', 'string', 'max:20'],
            'dob' => ['sometimes', 'nullable', 'date'],
            'gender' => ['sometimes', 'nullable', 'string', Rule::in(['male', 'female', 'other'])],
            'address' => ['sometimes', 'nullable', 'string'],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'state' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pincode' => ['sometimes', 'nullable', 'string', 'max:20'],
            'status' => ['sometimes', 'nullable', 'string'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
            'role_ids' => ['sometimes', 'nullable', 'array'],
            'role_ids.*' => ['integer', new ModelExists(Role::class)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resource(User $user, bool $includePermissions = false): array
    {
        $roles = $user->relationLoaded('roles')
            ? $user->roles->map(function (Model $role): array {
                assert($role instanceof Role);

                return [
                    'id' => (int) $role->id,
                    'name' => (string) $role->name,
                ];
            })->values()->all()
            : [];

        $payload = [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'contact' => $user->contact ? (string) $user->contact : null,
            'gender' => $user->gender ? (string) $user->gender : null,
            'dob' => $user->dob?->toDateString(),
            'status' => Status::valueOf($user->status),
            'photo' => $user->photo ? (string) $user->photo : null,
            'photo_url' => $user->photo ? Storage::disk('public')->url((string) $user->photo) : null,
            'address' => $user->address ? (string) $user->address : null,
            'country' => $user->country ? (string) $user->country : null,
            'state' => $user->state ? (string) $user->state : null,
            'city' => $user->city ? (string) $user->city : null,
            'pincode' => $user->pincode ? (string) $user->pincode : null,
            'roles' => $roles,
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];

        if ($includePermissions) {
            $payload['permissions'] = $user->getAllPermissions()->pluck('name')->values()->all();
        }

        return $payload;
    }
}
