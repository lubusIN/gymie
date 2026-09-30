<?php

namespace App\Filament\Resources\WhatsappMessages\Pages;

use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWhatsappMessage extends EditRecord
{
    protected static string $resource = WhatsappMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
