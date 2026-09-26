<?php

namespace App\Filament\Resources\Enquiries\Pages;

use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Models\Enquiry;
use App\Support\Filament\RecordPageActions;
use Filament\Resources\Pages\EditRecord;

/**
 * @property-read Enquiry $record
 */
class EditEnquiry extends EditRecord
{
    protected static string $resource = EnquiryResource::class;

    public function getTitle(): string
    {
        return __('app.actions.edit', ['resource' => EnquiryResource::getModelLabel()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            RecordPageActions::view(),
            RecordPageActions::delete(),
            RecordPageActions::forceDelete(),
            RecordPageActions::restore(),
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
