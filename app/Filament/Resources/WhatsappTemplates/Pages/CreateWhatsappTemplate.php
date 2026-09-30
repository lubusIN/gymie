<?php

namespace App\Filament\Resources\WhatsappTemplates\Pages;

use App\Filament\Resources\WhatsappTemplates\WhatsappTemplateResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use App\Services\WhatsApp\WhatsAppClient;
use Filament\Notifications\Notification;

class CreateWhatsappTemplate extends CreateRecord
{
    protected static string $resource = WhatsappTemplateResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $client = new WhatsAppClient();
        
        $exampleValues = collect($data['example_values'] ?? [])->pluck('value')->toArray();
        $data['example_values'] = $exampleValues;
        
        $response = $client->createTemplate(
            $data['name'],
            $data['language'],
            $data['category'],
            $data['body'],
            $exampleValues
        );
        
        if ($response->successful()) {
            $responseData = $response->json();
            $data['meta_template_id'] = $responseData['id'] ?? null;
            $data['status'] = $responseData['status'] ?? 'PENDING';
            $data['meta_response'] = $responseData;
            
            Notification::make()
                ->title('Template Created Successfully')
                ->success()
                ->send();
        } else {
            $data['status'] = 'FAILED_LOCAL';
            $data['meta_response'] = $response->json();
            
            Notification::make()
                ->title('Failed to create template')
                ->body($response->json('error.message') ?? 'Unknown error')
                ->danger()
                ->send();
        }
        
        return static::getModel()::create($data);
    }
}
