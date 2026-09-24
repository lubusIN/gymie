<?php

namespace App\Filament\Resources\Enquiries\Pages;

use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Models\Enquiry;
use App\Support\Filament\EnquiryPipelineActions;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * @property-read Enquiry $record
 */
class ViewEnquiry extends ViewRecord
{
    protected static string $resource = EnquiryResource::class;

    public function getTitle(): string
    {
        return __('app.titles.record', [
            'resource' => EnquiryResource::getModelLabel(),
            'name' => $this->record->name,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
            ActionGroup::make([
                EnquiryPipelineActions::markAsContacted(),
                EnquiryPipelineActions::scheduleFollowUp(),
                EnquiryPipelineActions::convertToMember(),
                EnquiryPipelineActions::markAsLost(),
                EnquiryPipelineActions::viewMember(),
            ]),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.sales'),
            EnquiryResource::getUrl('index') => EnquiryResource::getNavigationLabel(),
            $this->record->name,
        ];
    }
}
