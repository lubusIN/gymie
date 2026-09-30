<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\WhatsappTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;

class WhatsappTemplatePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WhatsappTemplate');
    }

    public function view(AuthUser $authUser, WhatsappTemplate $whatsappTemplate): bool
    {
        return $authUser->can('View:WhatsappTemplate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WhatsappTemplate');
    }

    public function update(AuthUser $authUser, WhatsappTemplate $whatsappTemplate): bool
    {
        return $authUser->can('Update:WhatsappTemplate');
    }

    public function delete(AuthUser $authUser, WhatsappTemplate $whatsappTemplate): bool
    {
        return $authUser->can('Delete:WhatsappTemplate');
    }

    public function restore(AuthUser $authUser, WhatsappTemplate $whatsappTemplate): bool
    {
        return $authUser->can('Restore:WhatsappTemplate');
    }

    public function forceDelete(AuthUser $authUser, WhatsappTemplate $whatsappTemplate): bool
    {
        return $authUser->can('ForceDelete:WhatsappTemplate');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:WhatsappTemplate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:WhatsappTemplate');
    }

    public function replicate(AuthUser $authUser, WhatsappTemplate $whatsappTemplate): bool
    {
        return $authUser->can('Replicate:WhatsappTemplate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:WhatsappTemplate');
    }

}