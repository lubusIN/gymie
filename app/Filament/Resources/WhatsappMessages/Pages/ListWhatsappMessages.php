<?php

namespace App\Filament\Resources\WhatsappMessages\Pages;

use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWhatsappMessages extends ListRecords
{
    protected static string $resource = WhatsappMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\WhatsAppConnectionStatus::class,
        ];
    }
}
