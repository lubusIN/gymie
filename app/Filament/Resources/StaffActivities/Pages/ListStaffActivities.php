<?php

namespace App\Filament\Resources\StaffActivities\Pages;

use App\Filament\Resources\StaffActivities\StaffActivityResource;
use Filament\Resources\Pages\ListRecords;

class ListStaffActivities extends ListRecords
{
    protected static string $resource = StaffActivityResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.administration'),
            StaffActivityResource::getNavigationLabel(),
        ];
    }
}
