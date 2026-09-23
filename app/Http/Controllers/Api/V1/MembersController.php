<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\MemberStoreRequest;
use App\Http\Requests\Api\V1\MemberUpdateRequest;
use App\Http\Resources\V1\MemberResource;
use App\Models\Member;
use App\Services\Api\QueryFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Members CRUD endpoints.
 */
class MembersController extends ApiController
{
    private const RESOURCE_KEY = 'members';

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->requirePermission($request, 'ViewAny:Member');

        $query = Member::query();

        QueryFilters::applyIndexFilters($query, $request, self::RESOURCE_KEY);

        $perPage = QueryFilters::perPage($request);

        return MemberResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(MemberStoreRequest $request): MemberResource
    {
        $this->requirePermission($request, 'Create:Member');

        $data = $request->validated();

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->storePublicly('images', 'public');
        }

        $member = Member::create($data);

        return new MemberResource($member->refresh());
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Member $member): MemberResource
    {
        $this->requirePermission($request, 'View:Member');

        return new MemberResource($member);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(MemberUpdateRequest $request, Member $member): MemberResource
    {
        $this->requirePermission($request, 'Update:Member');

        $data = $request->validated();

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->storePublicly('images', 'public');
        }

        $member->update($data);

        return new MemberResource($member->refresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Member $member): JsonResponse
    {
        return $this->deleteModel($request, 'Delete:Member', $member);
    }

    /**
     * Restore a soft deleted member.
     */
    public function restore(Request $request, int $member): MemberResource
    {
        $record = $this->restoreSoftDeleted($request, 'RestoreAny:Member', Member::class, $member);

        return new MemberResource($record->refresh());
    }

    /**
     * Permanently delete a member.
     */
    public function forceDelete(Request $request, int $member): JsonResponse
    {
        $this->forceDeleteSoftDeleted($request, 'ForceDeleteAny:Member', Member::class, $member);

        return $this->noContent();
    }
}
