<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\EnquiryStoreRequest;
use App\Http\Requests\Api\V1\EnquiryUpdateRequest;
use App\Http\Resources\V1\EnquiryResource;
use App\Models\Enquiry;
use App\Services\Api\QueryFilters;
use App\Support\AppConfig;
use App\Support\Data;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Enquiries CRUD endpoints.
 */
class EnquiriesController extends ApiController
{
    private const RESOURCE_KEY = 'enquiries';

    /**
     * Display a listing of enquiries.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->requirePermission($request, 'ViewAny:Enquiry');

        $query = Enquiry::query();

        QueryFilters::applyIndexFilters($query, $request, self::RESOURCE_KEY);

        $perPage = QueryFilters::perPage($request);

        return EnquiryResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created enquiry.
     */
    public function store(EnquiryStoreRequest $request): EnquiryResource
    {
        $this->requirePermission($request, 'Create:Enquiry');

        $data = $request->validated();
        $data['user_id'] = Data::int($this->currentUser($request)->getAuthIdentifier());

        $followUp = $data['follow_up'] ?? null;
        unset($data['follow_up']);

        $data['date'] = $data['date'] ?? now()->timezone(AppConfig::timezone())->toDateString();

        $enquiry = Enquiry::create($data);

        if (is_array($followUp)) {
            $enquiry->followUps()->create([
                'user_id' => $data['user_id'],
                'schedule_date' => $followUp['schedule_date'],
                'method' => $followUp['method'],
                'status' => 'pending',
            ]);
        }

        return new EnquiryResource($enquiry->refresh());
    }

    /**
     * Display an enquiry.
     */
    public function show(Request $request, Enquiry $enquiry): EnquiryResource
    {
        $this->requirePermission($request, 'View:Enquiry');

        return new EnquiryResource($enquiry);
    }

    /**
     * Update an enquiry.
     */
    public function update(EnquiryUpdateRequest $request, Enquiry $enquiry): EnquiryResource
    {
        $this->requirePermission($request, 'Update:Enquiry');

        $enquiry->update($request->validated());

        return new EnquiryResource($enquiry->refresh());
    }

    /**
     * Soft delete an enquiry.
     */
    public function destroy(Request $request, Enquiry $enquiry): JsonResponse
    {
        return $this->deleteModel($request, 'Delete:Enquiry', $enquiry);
    }

    /**
     * Restore a soft deleted enquiry.
     */
    public function restore(Request $request, int $enquiry): EnquiryResource
    {
        $record = $this->restoreSoftDeleted($request, 'RestoreAny:Enquiry', Enquiry::class, $enquiry);

        return new EnquiryResource($record->refresh());
    }

    /**
     * Permanently delete an enquiry.
     */
    public function forceDelete(Request $request, int $enquiry): JsonResponse
    {
        $this->forceDeleteSoftDeleted($request, 'ForceDeleteAny:Enquiry', Enquiry::class, $enquiry);

        return $this->noContent();
    }
}
