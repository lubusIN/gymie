<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\UserStoreRequest;
use App\Http\Requests\Api\V1\UserUpdateRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Services\Api\QueryFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Users CRUD endpoints.
 */
class UsersController extends ApiController
{
    private const RESOURCE_KEY = 'users';

    /**
     * Display a listing of users.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->requirePermission($request, 'ViewAny:User');

        $query = User::query()->with('roles');

        QueryFilters::applyIndexFilters($query, $request, self::RESOURCE_KEY);

        $perPage = QueryFilters::perPage($request);

        return UserResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UserStoreRequest $request): UserResource
    {
        $this->requirePermission($request, 'Create:User');

        $data = $request->validated();
        $roleIds = $data['role_ids'] ?? null;
        unset($data['role_ids']);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->storePublicly('images', 'public');
        }

        $user = User::create($data);

        $this->syncUserRoles($request, $user, $roleIds);

        $user->load('roles');

        return new UserResource($user);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, User $user): UserResource
    {
        $this->requirePermission($request, 'View:User');

        $user->load('roles');

        return new UserResource($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UserUpdateRequest $request, User $user): UserResource
    {
        $this->requirePermission($request, 'Update:User');

        $data = $request->validated();
        $roleIds = $data['role_ids'] ?? null;
        unset($data['role_ids']);

        if (array_key_exists('password', $data) && blank($data['password'])) {
            unset($data['password']);
        }

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->storePublicly('images', 'public');
        }

        $user->update($data);

        $this->syncUserRoles($request, $user, $roleIds);

        $user->load('roles');

        return new UserResource($user);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        return $this->deleteModel($request, 'Delete:User', $user);
    }

    /**
     * Restore a soft deleted user.
     */
    public function restore(Request $request, int $user): UserResource
    {
        $record = $this->restoreSoftDeleted($request, 'RestoreAny:User', User::class, $user);
        $record->load('roles');

        return new UserResource($record->refresh());
    }

    /**
     * Permanently delete a user.
     */
    public function forceDelete(Request $request, int $user): JsonResponse
    {
        $this->forceDeleteSoftDeleted($request, 'ForceDeleteAny:User', User::class, $user);

        return $this->noContent();
    }

    /**
     * Sync roles for a user securely.
     */
    private function syncUserRoles(Request $request, User $user, ?array $roleIds): void
    {
        if (! is_array($roleIds)) {
            return;
        }

        $this->requirePermission($request, 'Update:Role');

        $superAdminRoleId = Role::whereName('super_admin')->first()?->id;

        if (! $request->user()->hasRole('super_admin') && in_array($superAdminRoleId, $roleIds)) {
            throw ValidationException::withMessages([
                'role_ids' => ['You cannot assign the super_admin role.'],
            ]);
        }

        $actorRoles = $request->user()->roles->pluck('id')->toArray();
        $allowedRoleIds = array_intersect($roleIds, $actorRoles);

        // Super admins can assign any role they request
        if ($request->user()->hasRole('super_admin')) {
            $allowedRoleIds = $roleIds;
        }

        $roles = Role::query()->whereIn('id', $allowedRoleIds)->get();
        $user->syncRoles($roles);
    }
}
