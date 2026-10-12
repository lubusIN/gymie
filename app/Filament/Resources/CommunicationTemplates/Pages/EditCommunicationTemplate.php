<?php

namespace App\Filament\Resources\CommunicationTemplates\Pages;

use App\Filament\Resources\CommunicationTemplates\CommunicationTemplateResource;
use App\Models\CommunicationTemplate;
use App\Support\Filament\RecordPageActions;
use Filament\Resources\Pages\EditRecord;

/**
 * @property-read CommunicationTemplate $record
 */
class EditCommunicationTemplate extends EditRecord
{
    protected static string $resource = CommunicationTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RecordPageActions::view(),
            RecordPageActions::delete()
                ->visible(fn (): bool => $this->record instanceof CommunicationTemplate && $this->record->canBeDeleted()),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.communication'),
            CommunicationTemplateResource::getUrl('index') => CommunicationTemplateResource::getNavigationLabel(),
            $this->record->name,
        ];
    }
}
