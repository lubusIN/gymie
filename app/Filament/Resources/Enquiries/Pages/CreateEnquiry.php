<?php

namespace App\Filament\Resources\Enquiries\Pages;

use App\Filament\Resources\Enquiries\EnquiryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEnquiry extends CreateRecord
{
    protected static string $resource = EnquiryResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return __('app.actions.new', ['resource' => EnquiryResource::getModelLabel()]);
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.sales'),
            EnquiryResource::getUrl('index') => EnquiryResource::getNavigationLabel(),
        ];
    }
}
