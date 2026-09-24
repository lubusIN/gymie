<?php

namespace App\Filament\Resources\Enquiries\Pages;

use App\Enums\Status;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Models\Enquiry;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListEnquiries extends ListRecords
{
    protected static string $resource = EnquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-m-plus')
                ->label(__('app.actions.new', ['resource' => EnquiryResource::getModelLabel()]))
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
        return [
            'all' => Tab::make(__('app.common.all')),
            Status::Lead->value => $this->statusTab(Status::Lead),
            Status::FollowUpScheduled->value => $this->statusTab(Status::FollowUpScheduled),
            Status::Member->value => $this->statusTab(Status::Member),
            Status::Lost->value => $this->statusTab(Status::Lost),
        ];
    }

    private function statusTab(Status $status): Tab
    {
        return Tab::make($status->getLabel())
            ->badge(Enquiry::query()->where('status', $status->value)->count())
            ->badgeColor($status->getColor())
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->where('status', $status->value),
            );
    }
}
