<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\PlanStoreRequest;
use App\Http\Requests\Api\V1\PlanUpdateRequest;
use App\Http\Resources\V1\PlanResource;
use App\Models\Plan;
use App\Services\Api\QueryFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Plans CRUD endpoints.
 */
class PlansController extends ApiController
{
    private const RESOURCE_KEY = 'plans';

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->requirePermission($request, 'ViewAny:Plan');

        $query = Plan::query()->with('service');

        QueryFilters::applyIndexFilters($query, $request, self::RESOURCE_KEY);

        $perPage = QueryFilters::perPage($request);

        return PlanResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PlanStoreRequest $request): PlanResource
    {
        $this->requirePermission($request, 'Create:Plan');

        $plan = Plan::create($request->validated());
        $plan->load('service');

        return new PlanResource($plan);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Plan $plan): PlanResource
    {
        $this->requirePermission($request, 'View:Plan');

        $plan->load('service');

        return new PlanResource($plan);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PlanUpdateRequest $request, Plan $plan): PlanResource
    {
        $this->requirePermission($request, 'Update:Plan');

        $plan->update($request->validated());
        $plan->load('service');

        return new PlanResource($plan);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Plan $plan): JsonResponse
    {
        return $this->deleteModel($request, 'Delete:Plan', $plan);
    }

    /**
     * Restore a soft deleted plan.
     */
    public function restore(Request $request, int $plan): PlanResource
    {
        $record = $this->restoreSoftDeleted($request, 'RestoreAny:Plan', Plan::class, $plan);
        $record->load('service');

        return new PlanResource($record->refresh());
    }

    /**
     * Permanently delete a plan.
     */
    public function forceDelete(Request $request, int $plan): JsonResponse
    {
        $this->forceDeleteSoftDeleted($request, 'ForceDeleteAny:Plan', Plan::class, $plan);

        return $this->noContent();
    }
}
