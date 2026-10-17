<?php

namespace App\Filament\Resources\StaffLeaves\Pages;

use App\Filament\Resources\StaffLeaves\StaffLeaveResource;
use App\Models\StaffLeave;
use App\Support\Filament\HasStatusHeading;
use App\Support\Filament\RecordPageActions;
use App\Support\Filament\StaffLeaveActions;
use Filament\Resources\Pages\ViewRecord;

/** @property-read StaffLeave $record */
class ViewStaffLeave extends ViewRecord
{
    use HasStatusHeading;

    protected static string $resource = StaffLeaveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            StaffLeaveActions::approve(),
            StaffLeaveActions::reject(),
            StaffLeaveActions::cancel(),
            RecordPageActions::edit(),
            RecordPageActions::delete(),
        ];
    }

    public function getTitle(): string
    {
        return __('app.titles.record', [
            'resource' => StaffLeaveResource::getModelLabel(),
            'name' => $this->record->staff->name,
        ]);
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.administration'),
            StaffLeaveResource::getUrl('index') => StaffLeaveResource::getNavigationLabel(),
            $this->record->staff->name,
        ];
    }
}
