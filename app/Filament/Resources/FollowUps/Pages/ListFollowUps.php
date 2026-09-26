<?php

namespace App\Filament\Resources\FollowUps\Pages;

use App\Enums\Status;
use App\Filament\Resources\FollowUps\FollowUpResource;
use App\Models\Enquiry;
use App\Models\FollowUp;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListFollowUps extends ListRecords
{
    protected static string $resource = FollowUpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus')
                ->label(__('app.actions.new_follow_up'))
                ->modalWidth('sm')
                ->modalHeading(__('app.actions.new_follow_up'))
                ->createAnother(false)
                ->hidden(! Enquiry::exists() || ! FollowUp::exists()),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.sales'),
            FollowUpResource::getNavigationLabel(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('app.common.all')),
            'pending' => Tab::make(__('app.status.pending'))
                ->badge(FollowUp::query()->where('status', 'pending')->count())
                ->badgeColor(Status::Pending->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'pending')),
            'done' => Tab::make(__('app.status.done'))
                ->badge(FollowUp::query()->where('status', 'done')->count())
                ->badgeColor(Status::Done->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'done')),
        ];
    }
}
