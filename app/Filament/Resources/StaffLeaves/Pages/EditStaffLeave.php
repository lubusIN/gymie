<?php

namespace App\Filament\Resources\StaffLeaves\Pages;

use App\Filament\Resources\StaffLeaves\StaffLeaveResource;
use App\Models\StaffLeave;
use App\Support\Filament\RecordPageActions;
use Filament\Resources\Pages\EditRecord;

/** @property-read StaffLeave $record */
class EditStaffLeave extends EditRecord
{
    protected static string $resource = StaffLeaveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RecordPageActions::view(),
            RecordPageActions::delete(),
        ];
    }

    public function getTitle(): string
    {
        return __('app.actions.edit', ['resource' => StaffLeaveResource::getModelLabel()]);
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.administration'),
            StaffLeaveResource::getUrl('index') => StaffLeaveResource::getNavigationLabel(),
            $this->record->staff->name,
        ];
    }

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! auth()->user()?->can('ViewAny:StaffLeave')) {
            $data['staff_id'] = $this->record->staff_id;
        }

        return $data;
    }
}
