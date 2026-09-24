<?php

namespace Database\Factories;

use App\Models\Enquiry;
use App\Models\Service;
use App\Models\User;
use Database\Factories\Concerns\WithSynchronizedLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enquiry>
 */
class EnquiryFactory extends Factory
{
    use WithSynchronizedLocation;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $location = $this->synchronizedLocation();

        return [
            'user_id' => User::factory(),
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'contact' => $location['contact'],
            'date' => $this->faker->date(now()),
            'gender' => $this->faker->randomElement(['male', 'female', 'other']),
            'dob' => $this->faker->date('d-m-Y', '-15 years'),
            'status' => 'lead',
            'lost_reason' => null,
            'converted_at' => null,
            'address' => $location['address'],
            'country' => $location['country'],
            'city' => $location['city'],
            'state' => $location['state'],
            'pincode' => $location['pincode'],
            'interested_in' => Service::inRandomOrder()->limit(rand(1, 5))->pluck('name')->toArray(),
            'source' => $this->faker->randomElement(['promotions', 'word_of_mouth', 'others']),
            'goal' => $this->faker->randomElement(['fitness', 'body_building', 'fatloss', 'weightgain', 'others']),
            'start_by' => $this->faker->dateTimeBetween('now', '+1 month')->format('d-m-Y'),
        ];
    }
}
