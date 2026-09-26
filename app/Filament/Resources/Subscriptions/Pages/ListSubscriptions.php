<?php

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Enums\Status;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Models\Subscription;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus')
                ->label(__('app.actions.new', ['resource' => SubscriptionResource::getModelLabel()]))
                ->visible(Subscription::exists()),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('app.common.all')),
            'upcoming' => Tab::make(__('app.status.upcoming'))
                ->badge(Subscription::query()->where('status', 'upcoming')->count())
                ->badgeColor(Status::Upcoming->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'upcoming')),
            'ongoing' => Tab::make(__('app.status.ongoing'))
                ->badge(Subscription::query()->where('status', 'ongoing')->count())
                ->badgeColor(Status::Ongoing->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'ongoing')),
            'expiring' => Tab::make(__('app.status.expiring'))
                ->badge(Subscription::query()->where('status', 'expiring')->count())
                ->badgeColor(Status::Expiring->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'expiring')),
            'expired' => Tab::make(__('app.status.expired'))
                ->badge(Subscription::query()->where('status', 'expired')->count())
                ->badgeColor(Status::Expired->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'expired')),
            'renewed' => Tab::make(__('app.status.renewed'))
                ->badge(Subscription::query()->where('status', 'renewed')->count())
                ->badgeColor(Status::Renewed->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'renewed')),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.memberships'),
            SubscriptionResource::getUrl('index') => SubscriptionResource::getNavigationLabel(),
        ];
    }
}
