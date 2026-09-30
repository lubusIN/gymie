<?php

namespace App\Filament\Resources\WhatsappTemplates\Pages;

use App\Filament\Resources\WhatsappTemplates\WhatsappTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use App\Services\WhatsApp\WhatsAppClient;
use App\Models\WhatsappTemplate;
use Filament\Notifications\Notification;

class ListWhatsappTemplates extends ListRecords
{
    protected static string $resource = WhatsappTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('Sync Templates')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $client = new WhatsAppClient();
                    $response = $client->getTemplates();

                    if ($response->successful()) {
                        $templates = $response->json('data') ?? [];
                        $count = 0;

                        foreach ($templates as $metaTemplate) {
                            $localTemplate = WhatsappTemplate::where('name', $metaTemplate['name'])
                                ->where('language', $metaTemplate['language'])
                                ->first();

                            if ($localTemplate) {
                                $localTemplate->update([
                                    'meta_template_id' => $metaTemplate['id'],
                                    'status' => $metaTemplate['status'],
                                    'meta_response' => $metaTemplate,
                                ]);
                                $count++;
                            }
                        }

                        Notification::make()
                            ->title('Templates Synced')
                            ->body("Successfully synced {$count} templates.")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Sync Failed')
                            ->body($response->json('error.message') ?? 'Unknown error')
                            ->danger()
                            ->send();
                    }
                }),
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
