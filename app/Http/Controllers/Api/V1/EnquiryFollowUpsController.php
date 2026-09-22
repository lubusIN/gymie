<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\EnquiryFollowUpStoreRequest;
use App\Http\Resources\V1\FollowUpResource;
use App\Models\Enquiry;
use App\Services\Api\QueryFilters;
use App\Support\Data;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Nested follow-ups under an enquiry.
 */
class EnquiryFollowUpsController extends ApiController
{
    /**
     * List follow-ups for an enquiry.
     */
    public function index(Request $request, Enquiry $enquiry): AnonymousResourceCollection
    {
        $this->requirePermission($request, 'View:Enquiry');

        $perPage = QueryFilters::perPage($request, default: 25);

        $rows = $enquiry->followUps()
            ->orderByDesc('schedule_date')
            ->paginate($perPage);

        return FollowUpResource::collection($rows);
    }

    /**
     * Create a follow-up for an enquiry.
     */
    public function store(EnquiryFollowUpStoreRequest $request, Enquiry $enquiry): FollowUpResource
    {
        $this->requirePermission($request, 'Update:Enquiry');

        $data = $request->validated();

        $followUp = $enquiry->followUps()->create([
            'user_id' => Data::int($this->currentUser($request)->getAuthIdentifier()),
            'schedule_date' => $data['schedule_date'],
            'method' => $data['method'],
            'outcome' => $data['outcome'] ?? null,
            'status' => $data['status'] ?? 'pending',
        ]);

        return new FollowUpResource($followUp->refresh());
    }
}
