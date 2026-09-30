<?php

namespace App\Filament\Resources\WhatsappMessages\Pages;

use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use App\Services\WhatsApp\WhatsAppClient;
use Filament\Notifications\Notification;

class CreateWhatsappMessage extends CreateRecord
{
    protected static string $resource = WhatsappMessageResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $client = new WhatsAppClient();
        
        $parameters = collect($data['parameters'] ?? [])->pluck('value')->toArray();
        $data['parameters'] = $parameters;
        
        $response = $client->sendTemplateMessage(
            $data['recipient'],
            $data['template_name'],
            $data['language'],
            $parameters
        );
        
        if ($response->successful()) {
            $responseData = $response->json();
            $data['meta_message_id'] = $responseData['messages'][0]['id'] ?? null;
            $data['status'] = 'sent';
            $data['meta_response'] = $responseData;
            $data['sent_at'] = now();
            
            Notification::make()
                ->title('Message Sent Successfully')
                ->success()
                ->send();
        } else {
            $data['status'] = 'failed';
            $data['meta_response'] = $response->json();
            
            Notification::make()
                ->title('Failed to send message')
                ->body($response->json('error.message') ?? 'Unknown error')
                ->danger()
                ->send();
        }
        
        return static::getModel()::create($data);
    }
}
