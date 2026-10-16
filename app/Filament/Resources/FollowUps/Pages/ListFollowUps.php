<?php

namespace App\Filament\Resources\FollowUps\Pages;

use App\Enums\Status;
use App\Filament\Pages\ListRecordsWithTableTabs;
use App\Filament\Resources\FollowUps\FollowUpResource;
use App\Models\Enquiry;
use App\Models\FollowUp;
use App\Support\Filament\TableUi;
use Filament\Actions\CreateAction;
use Filament\Support\Enums\Width;

class ListFollowUps extends ListRecordsWithTableTabs
{
    protected static string $resource = FollowUpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth(Width::Small)
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
        return TableUi::statusTabs(FollowUp::class, [Status::Pending, Status::Done]);
    }
}
