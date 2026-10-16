<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\Status;
use App\Filament\Pages\ListRecordsWithTableTabs;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\Filament\TableUi;
use Filament\Actions\CreateAction;

class ListUsers extends ListRecordsWithTableTabs
{
    protected static string $resource = UserResource::class;

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
            UserResource::getNavigationLabel(),
        ];
    }

    public function getTabs(): array
    {
        return TableUi::statusTabs(User::class, [Status::Active, Status::Inactive]);
    }
}
