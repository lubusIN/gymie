<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\TenantContext;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Support\Data;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Authentication endpoints for API v1 (Sanctum bearer tokens).
 */
class AuthController extends ApiController
{
    /**
     * Create a Sanctum bearer token for a user.
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request, TenantContext $tenantContext): JsonResponse
    {
        $user = User::query()
            ->where('email', $request->string('email')->toString())
            ->first();

        if ($user && $user->status?->value === 'inactive') {
            throw ValidationException::withMessages([
                'email' => ['This account has been deactivated.'],
            ]);
        }

        if (
            ! $user
            || ($tenantContext->gymId() && Data::int($user->gym_id) !== $tenantContext->gymId())
            || ! Hash::check($request->string('password')->toString(), Data::string($user->password))
        ) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $deviceName = $request->string('device_name')->trim();

        if ($deviceName->isEmpty()) {
            $deviceName = str($request->userAgent() ?? 'api');
        }

        $deviceName = $deviceName->limit(255, '')->toString();

        $token = $user->createToken($deviceName)->plainTextToken;

        $user->load('roles');

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Return the authenticated user (includes roles and permissions).
     */
    public function me(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        $user->load('roles');

        return new UserResource($user);
    }

    /**
     * Revoke the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($request->bearerToken() !== null) {
            $user->currentAccessToken()->delete();
        } else {
            $user->tokens()->delete();
        }

        return $this->noContent();
    }
}
