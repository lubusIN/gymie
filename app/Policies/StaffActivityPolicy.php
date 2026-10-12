<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StaffActivity;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class StaffActivityPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:StaffActivity');
    }

    public function view(User $user, StaffActivity $staffActivity): bool
    {
        return $user->can('View:StaffActivity');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, StaffActivity $staffActivity): bool
    {
        return false;
    }

    public function delete(User $user, StaffActivity $staffActivity): bool
    {
        return false;
    }

    public function restore(User $user, StaffActivity $staffActivity): bool
    {
        return false;
    }

    public function forceDelete(User $user, StaffActivity $staffActivity): bool
    {
        return false;
    }
}
