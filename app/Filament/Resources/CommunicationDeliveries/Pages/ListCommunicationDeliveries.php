<?php

namespace App\Filament\Resources\CommunicationDeliveries\Pages;

use App\Filament\Resources\CommunicationDeliveries\CommunicationDeliveryResource;
use Filament\Resources\Pages\ListRecords;

class ListCommunicationDeliveries extends ListRecords
{
    protected static string $resource = CommunicationDeliveryResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.communication'),
            CommunicationDeliveryResource::getNavigationLabel(),
        ];
    }
}
