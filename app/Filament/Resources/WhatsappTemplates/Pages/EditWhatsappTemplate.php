<?php

namespace App\Filament\Resources\WhatsappTemplates\Pages;

use App\Filament\Resources\WhatsappTemplates\WhatsappTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWhatsappTemplate extends EditRecord
{
    protected static string $resource = WhatsappTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
