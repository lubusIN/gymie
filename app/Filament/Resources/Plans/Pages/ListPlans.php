<?php

namespace App\Filament\Resources\Plans\Pages;

use App\Enums\Status;
use App\Filament\Pages\ListRecordsWithTableTabs;
use App\Filament\Resources\Plans\PlanResource;
use App\Models\Plan;
use App\Support\Filament\TableUi;
use Filament\Actions\CreateAction;
use Filament\Support\Enums\Width;

class ListPlans extends ListRecordsWithTableTabs
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth(Width::ExtraLarge)
                ->modalHeading(__('app.actions.new', ['resource' => PlanResource::getModelLabel()]))
                ->createAnother(false)
                ->visible(Plan::exists()),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.memberships'),
            PlanResource::getNavigationLabel(),
        ];
    }

    public function getTabs(): array
    {
        return TableUi::statusTabs(Plan::class, [Status::Active, Status::Inactive]);
    }
}
