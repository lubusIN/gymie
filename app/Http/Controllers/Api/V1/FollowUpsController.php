<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\FollowUpStoreRequest;
use App\Http\Requests\Api\V1\FollowUpUpdateRequest;
use App\Http\Resources\V1\FollowUpResource;
use App\Models\FollowUp;
use App\Services\Api\QueryFilters;
use App\Support\Data;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Follow-ups CRUD endpoints.
 */
class FollowUpsController extends ApiController
{
    private const RESOURCE_KEY = 'follow-ups';

    /**
     * Display a listing of follow-ups.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->requirePermission($request, 'ViewAny:FollowUp');

        $query = FollowUp::query();

        QueryFilters::applyIndexFilters($query, $request, self::RESOURCE_KEY);

        $perPage = QueryFilters::perPage($request);

        return FollowUpResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created follow-up.
     */
    public function store(FollowUpStoreRequest $request): FollowUpResource
    {
        $this->requirePermission($request, 'Create:FollowUp');

        $data = $request->validated();
        $data['user_id'] = Data::int($this->currentUser($request)->getAuthIdentifier());

        $followUp = FollowUp::create($data);

        return new FollowUpResource($followUp->refresh());
    }

    /**
     * Display a follow-up.
     */
    public function show(Request $request, FollowUp $followUp): FollowUpResource
    {
        $this->requirePermission($request, 'View:FollowUp');

        return new FollowUpResource($followUp);
    }

    /**
     * Update a follow-up.
     */
    public function update(FollowUpUpdateRequest $request, FollowUp $followUp): FollowUpResource
    {
        $this->requirePermission($request, 'Update:FollowUp');

        $followUp->update($request->validated());

        return new FollowUpResource($followUp->refresh());
    }

    /**
     * Soft delete a follow-up.
     */
    public function destroy(Request $request, FollowUp $followUp): JsonResponse
    {
        return $this->deleteModel($request, 'Delete:FollowUp', $followUp);
    }

    /**
     * Restore a soft deleted follow-up.
     */
    public function restore(Request $request, int $followUp): FollowUpResource
    {
        $record = $this->restoreSoftDeleted($request, 'RestoreAny:FollowUp', FollowUp::class, $followUp);

        return new FollowUpResource($record->refresh());
    }

    /**
     * Permanently delete a follow-up.
     */
    public function forceDelete(Request $request, int $followUp): JsonResponse
    {
        $this->forceDeleteSoftDeleted($request, 'ForceDeleteAny:FollowUp', FollowUp::class, $followUp);

        return $this->noContent();
    }
}
