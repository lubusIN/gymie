<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\WhatsappMessage;
use Illuminate\Auth\Access\HandlesAuthorization;

class WhatsappMessagePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WhatsappMessage');
    }

    public function view(AuthUser $authUser, WhatsappMessage $whatsappMessage): bool
    {
        return $authUser->can('View:WhatsappMessage');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WhatsappMessage');
    }

    public function update(AuthUser $authUser, WhatsappMessage $whatsappMessage): bool
    {
        return $authUser->can('Update:WhatsappMessage');
    }

    public function delete(AuthUser $authUser, WhatsappMessage $whatsappMessage): bool
    {
        return $authUser->can('Delete:WhatsappMessage');
    }

    public function restore(AuthUser $authUser, WhatsappMessage $whatsappMessage): bool
    {
        return $authUser->can('Restore:WhatsappMessage');
    }

    public function forceDelete(AuthUser $authUser, WhatsappMessage $whatsappMessage): bool
    {
        return $authUser->can('ForceDelete:WhatsappMessage');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:WhatsappMessage');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:WhatsappMessage');
    }

    public function replicate(AuthUser $authUser, WhatsappMessage $whatsappMessage): bool
    {
        return $authUser->can('Replicate:WhatsappMessage');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:WhatsappMessage');
    }

}