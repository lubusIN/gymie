<?php

namespace App\Filament\Resources\Plans\Pages;

use App\Enums\Status;
use App\Filament\Resources\Plans\PlanResource;
use App\Models\Plan;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPlans extends ListRecords
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus')
                ->modalAlignment('center')
                ->modalWidth('xl')
                ->label(__('app.actions.new', ['resource' => PlanResource::getModelLabel()]))
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
        return [
            'all' => Tab::make(__('app.common.all')),
            'active' => Tab::make(__('app.status.active'))
                ->badge(Plan::query()->where('status', 'active')->count())
                ->badgeColor(Status::Active->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'active')),
            'inactive' => Tab::make(__('app.status.inactive'))
                ->badge(Plan::query()->where('status', 'inactive')->count())
                ->badgeColor(Status::Inactive->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'inactive')),
        ];
    }
}
