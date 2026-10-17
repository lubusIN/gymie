<?php

namespace App\Filament\Resources\StaffLeaves\Pages;

use App\Enums\Status;
use App\Filament\Pages\ListRecordsWithTableTabs;
use App\Filament\Resources\StaffLeaves\StaffLeaveResource;
use App\Models\StaffLeave;
use App\Support\Filament\TableUi;
use Filament\Actions\CreateAction;
use Illuminate\Database\Eloquent\Builder;

class ListStaffLeaves extends ListRecordsWithTableTabs
{
    protected static string $resource = StaffLeaveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.administration'),
            StaffLeaveResource::getNavigationLabel(),
        ];
    }

    public function getTabs(): array
    {
        return TableUi::statusTabs(
            StaffLeave::class,
            [Status::Pending, Status::Approved, Status::Rejected, Status::Cancelled],
            fn (Builder $query): Builder => auth()->user()?->can('ViewAny:StaffLeave')
                ? $query
                : $query->where('staff_id', auth()->id()),
        );
    }
}
