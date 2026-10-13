<?php

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Enums\Status;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Models\Subscription;
use App\Support\Filament\TableUi;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(Subscription::exists()),
        ];
    }

    public function getTabs(): array
    {
        return TableUi::statusTabs(Subscription::class, [
            Status::Upcoming,
            Status::Ongoing,
            Status::Expiring,
            Status::Expired,
            Status::Renewed,
        ]);
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.memberships'),
            SubscriptionResource::getUrl('index') => SubscriptionResource::getNavigationLabel(),
        ];
    }
}
