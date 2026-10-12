<?php

namespace App\Policies;

use App\Models\CommunicationDelivery;
use App\Models\User;

class CommunicationDeliveryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:CommunicationDelivery');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CommunicationDelivery $communicationDelivery): bool
    {
        return $user->can('View:CommunicationDelivery');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CommunicationDelivery $communicationDelivery): bool
    {
        return $user->can('Update:CommunicationDelivery');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CommunicationDelivery $communicationDelivery): bool
    {
        return $user->can('Delete:CommunicationDelivery');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CommunicationDelivery $communicationDelivery): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CommunicationDelivery $communicationDelivery): bool
    {
        return false;
    }
}
