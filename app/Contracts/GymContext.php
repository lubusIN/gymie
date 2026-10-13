<?php

namespace App\Contracts;

/**
 * Provides the active gym identifier when an installation supplies one.
 */
interface GymContext
{
    public function gymId(): ?int;
}
