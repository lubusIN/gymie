<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ServiceStoreRequest;
use App\Http\Requests\Api\V1\ServiceUpdateRequest;
use App\Http\Resources\V1\ServiceResource;
use App\Models\Service;
use App\Services\Api\QueryFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Services CRUD endpoints.
 */
class ServicesController extends ApiController
{
    private const RESOURCE_KEY = 'services';

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->requirePermission($request, 'ViewAny:Service');

        $query = Service::query();

        QueryFilters::applyIndexFilters($query, $request, self::RESOURCE_KEY);

        $perPage = QueryFilters::perPage($request);

        return ServiceResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ServiceStoreRequest $request): ServiceResource
    {
        $this->requirePermission($request, 'Create:Service');

        $service = Service::create($request->validated());

        return new ServiceResource($service->refresh());
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Service $service): ServiceResource
    {
        $this->requirePermission($request, 'View:Service');

        return new ServiceResource($service);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ServiceUpdateRequest $request, Service $service): ServiceResource
    {
        $this->requirePermission($request, 'Update:Service');

        $service->update($request->validated());

        return new ServiceResource($service->refresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Service $service): JsonResponse
    {
        return $this->deleteModel($request, 'Delete:Service', $service);
    }

    /**
     * Restore a soft deleted service.
     */
    public function restore(Request $request, int $service): ServiceResource
    {
        $record = $this->restoreSoftDeleted($request, 'RestoreAny:Service', Service::class, $service);

        return new ServiceResource($record->refresh());
    }

    /**
     * Permanently delete a service.
     */
    public function forceDelete(Request $request, int $service): JsonResponse
    {
        $this->forceDeleteSoftDeleted($request, 'ForceDeleteAny:Service', Service::class, $service);

        return $this->noContent();
    }
}
