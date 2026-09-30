<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

class WhatsAppClient
{
    protected string $token;
    protected string $wabaId;
    protected string $phoneNumberId;
    protected string $graphVersion;

    public function __construct(
        ?string $token = null,
        ?string $wabaId = null,
        ?string $phoneNumberId = null,
        ?string $graphVersion = null
    ) {
        $this->token = $token ?? config('services.whatsapp.access_token', '');
        $this->wabaId = $wabaId ?? config('services.whatsapp.waba_id', '');
        $this->phoneNumberId = $phoneNumberId ?? config('services.whatsapp.phone_number_id', '');
        $this->graphVersion = $graphVersion ?? config('services.whatsapp.graph_version', 'v20.0');
    }

    protected function client(): PendingRequest
    {
        return Http::withToken($this->token)
            ->baseUrl("https://graph.facebook.com/{$this->graphVersion}");
    }

    public function sendTemplateMessage(string $recipient, string $templateName, string $languageCode = 'en_US', array $parameters = [])
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $recipient,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode,
                ],
            ],
        ];

        if (!empty($parameters)) {
            $bodyParams = [];
            foreach ($parameters as $param) {
                // If it's just a string, format it as a text param. 
                // In a more robust system, we would handle different component types.
                $bodyParams[] = [
                    'type' => 'text',
                    'text' => (string) $param,
                ];
            }
            
            if (count($bodyParams) > 0) {
                $payload['template']['components'] = [
                    [
                        'type' => 'body',
                        'parameters' => $bodyParams,
                    ]
                ];
            }
        }

        return $this->client()->post("/{$this->phoneNumberId}/messages", $payload);
    }

    public function createTemplate(string $name, string $language, string $category, string $body, ?array $exampleValues = [])
    {
        $payload = [
            'name' => $name,
            'language' => $language,
            'category' => $category,
            'components' => [
                [
                    'type' => 'BODY',
                    'text' => $body,
                ]
            ]
        ];

        if (!empty($exampleValues)) {
            $payload['components'][0]['example'] = [
                'body_text' => [array_values($exampleValues)]
            ];
        }

        return $this->client()->post("/{$this->wabaId}/message_templates", $payload);
    }

    public function getTemplates()
    {
        return $this->client()->get("/{$this->wabaId}/message_templates");
    }

    public function deleteTemplate(string $name)
    {
        return $this->client()->delete("/{$this->wabaId}/message_templates", [
            'name' => $name,
        ]);
    }
}
