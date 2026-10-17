<?php

namespace App\Filament\Resources\StaffLeaves\Pages;

use App\Filament\Resources\StaffLeaves\StaffLeaveResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStaffLeave extends CreateRecord
{
    protected static string $resource = StaffLeaveResource::class;

    public function getTitle(): string
    {
        return __('app.actions.new', ['resource' => StaffLeaveResource::getModelLabel()]);
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.administration'),
            StaffLeaveResource::getUrl('index') => StaffLeaveResource::getNavigationLabel(),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! auth()->user()?->can('ViewAny:StaffLeave')) {
            $data['staff_id'] = auth()->id();
        }

        return $data;
    }
}
