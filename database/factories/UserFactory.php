<?php

namespace Database\Factories;

use App\Contracts\GymContext;
use App\Models\User;
use Database\Factories\Concerns\WithSynchronizedLocation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    use WithSynchronizedLocation;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    public $status;

    public $gender;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $this->status = 'active';
        $this->gender = $this->faker->randomElement(['male', 'female', 'other']);
        $location = $this->synchronizedLocation();

        $attributes = [
            'name' => $this->faker->company,
            'contact' => $location['contact'],
            'emergency_contact' => $location['contact'],
            'address' => $location['address'],
            'country' => $location['country'],
            'state' => $location['state'],
            'city' => $location['city'],
            'pincode' => $location['pincode'],
            'gender' => $this->gender,
            'status' => $this->status,
            'dob' => $this->faker->date(),
            'joining_date' => $this->faker->dateTimeBetween('-5 years')->format('Y-m-d'),
            'email' => $this->faker->unique()->safeEmail,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];

        if (Schema::hasColumn('users', 'gym_id')) {
            $attributes['gym_id'] = app()->bound(GymContext::class)
                ? app(GymContext::class)->gymId()
                : null;
        }

        return $attributes;
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
