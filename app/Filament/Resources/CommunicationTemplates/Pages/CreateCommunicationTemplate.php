<?php

namespace App\Filament\Resources\CommunicationTemplates\Pages;

use App\Filament\Resources\CommunicationTemplates\CommunicationTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCommunicationTemplate extends CreateRecord
{
    protected static string $resource = CommunicationTemplateResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.communication'),
            CommunicationTemplateResource::getUrl('index') => CommunicationTemplateResource::getNavigationLabel(),
        ];
    }
}
