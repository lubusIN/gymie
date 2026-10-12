<?php

namespace App\Policies;

use App\Models\StaffAttendance;
use App\Models\User;

class StaffAttendancePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Attendance');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, StaffAttendance $staffAttendance): bool
    {
        return $user->can('View:Attendance');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('Create:Attendance');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, StaffAttendance $staffAttendance): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StaffAttendance $staffAttendance): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, StaffAttendance $staffAttendance): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, StaffAttendance $staffAttendance): bool
    {
        return false;
    }
}
