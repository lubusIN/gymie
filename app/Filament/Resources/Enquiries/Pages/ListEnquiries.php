<?php

namespace App\Filament\Resources\Enquiries\Pages;

use App\Enums\Status;
use App\Filament\Pages\ListRecordsWithTableTabs;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Models\Enquiry;
use App\Support\Filament\TableUi;
use Filament\Actions\CreateAction;

class ListEnquiries extends ListRecordsWithTableTabs
{
    protected static string $resource = EnquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->hidden(! Enquiry::exists()),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.sales'),
            EnquiryResource::getNavigationLabel(),
        ];
    }

    public function getTabs(): array
    {
        return TableUi::statusTabs(Enquiry::class, [
            Status::Lead,
            Status::FollowUpScheduled,
            Status::Member,
            Status::Lost,
        ]);
    }
}
