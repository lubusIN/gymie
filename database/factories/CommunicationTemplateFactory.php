<?php

namespace Database\Factories;

use App\Enums\CommunicationTemplateKey;
use App\Models\CommunicationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunicationTemplate>
 */
class CommunicationTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = fake()->randomElement(CommunicationTemplateKey::cases());

        return [
            'key' => $key->value,
            'event' => $key,
            'name' => $key->getLabel(),
            'channel' => 'email',
            'subject' => $key->defaultSubject(),
            'body' => $key->defaultBody(),
        ];
    }

    /**
     * Create an administrator-defined template.
     */
    public function custom(): static
    {
        return $this->state(fn (): array => [
            'key' => null,
            'event' => CommunicationTemplateKey::MemberWelcome,
            'name' => fake()->words(3, true),
            'channel' => 'email',
            'subject' => 'Hello {{ member_name }}',
            'body' => 'A message for {{ member_name }}.',
        ]);
    }
}
