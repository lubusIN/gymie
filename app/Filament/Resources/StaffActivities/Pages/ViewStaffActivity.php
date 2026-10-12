<?php

namespace App\Filament\Resources\StaffActivities\Pages;

use App\Filament\Resources\StaffActivities\StaffActivityResource;
use App\Models\StaffActivity;
use Filament\Resources\Pages\ViewRecord;

/** @property-read StaffActivity $record */
class ViewStaffActivity extends ViewRecord
{
    protected static string $resource = StaffActivityResource::class;

    public function getTitle(): string
    {
        return __('app.staff_activity.title', [
            'staff' => $this->record->causer?->name ?? __('app.placeholders.na'),
            'action' => $this->record->actionLabel(),
        ]);
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.administration'),
            StaffActivityResource::getUrl() => StaffActivityResource::getNavigationLabel(),
            $this->record->subjectLabel(),
        ];
    }
}
