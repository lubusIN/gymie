<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\SettingsRepository;
use App\Http\Requests\Api\V1\SettingsUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Settings read/write endpoints.
 */
class SettingsController extends ApiController
{
    public function __construct(private readonly SettingsRepository $settingsRepository) {}

    /**
     * Get the persisted settings JSON.
     */
    public function show(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'View:Settings');

        $settings = $this->settingsRepository->get();

        return response()->json([
            'data' => $settings,
        ]);
    }

    /**
     * Update (merge) settings JSON.
     */
    public function update(SettingsUpdateRequest $request): JsonResponse
    {
        $this->requirePermission($request, 'Update:Settings');

        $existing = $this->settingsRepository->get();
        $validated = $request->validated();

        $updated = array_replace_recursive($existing, $validated);

        $this->settingsRepository->put($updated);

        return response()->json([
            'data' => $this->settingsRepository->get(),
        ]);
    }
}
