<?php

namespace App\Policies;

use App\Enums\Status;
use App\Models\StaffLeave;
use App\Models\User;

class StaffLeavePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, StaffLeave $staffLeave): bool
    {
        return $staffLeave->staff_id === $user->getKey()
            || $user->can('View:StaffLeave');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, StaffLeave $staffLeave): bool
    {
        return $staffLeave->isPending()
            && ($staffLeave->staff_id === $user->getKey() || $user->can('Update:StaffLeave'));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StaffLeave $staffLeave): bool
    {
        return $staffLeave->isPending()
            && ($staffLeave->staff_id === $user->getKey() || $user->can('Delete:StaffLeave'));
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, StaffLeave $staffLeave): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, StaffLeave $staffLeave): bool
    {
        return false;
    }

    public function review(User $user, StaffLeave $staffLeave): bool
    {
        return $staffLeave->canBeReviewedBy($user)
            && $user->can('Update:StaffLeave');
    }

    public function cancel(User $user, StaffLeave $staffLeave): bool
    {
        if (! $staffLeave->canBeCancelledBy($user)) {
            return false;
        }

        return match ($staffLeave->status) {
            Status::Pending => true,
            Status::Approved => $user->can('Update:StaffLeave'),
            default => false,
        };
    }
}
