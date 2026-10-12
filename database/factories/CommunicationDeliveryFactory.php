<?php

namespace Database\Factories;

use App\Enums\CommunicationDeliveryStatus;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunicationDelivery>
 */
class CommunicationDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'communication_template_id' => CommunicationTemplate::query()->value('id') ?? CommunicationTemplate::factory(),
            'channel' => 'email',
            'recipient' => fake()->safeEmail(),
            'subject' => fake()->sentence(),
            'status' => CommunicationDeliveryStatus::Queued,
            'queued_at' => now(),
        ];
    }
}
