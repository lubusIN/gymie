<?php

namespace App\Services;

use App\Contracts\GymContext;

/**
 * Default gym context for a standalone installation.
 */
final class LocalGymContext implements GymContext
{
    public function gymId(): ?int
    {
        return null;
    }
}
