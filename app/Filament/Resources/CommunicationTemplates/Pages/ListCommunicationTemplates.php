<?php

namespace App\Filament\Resources\CommunicationTemplates\Pages;

use App\Filament\Resources\CommunicationTemplates\CommunicationTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCommunicationTemplates extends ListRecords
{
    protected static string $resource = CommunicationTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.communication'),
            CommunicationTemplateResource::getNavigationLabel(),
        ];
    }
}
