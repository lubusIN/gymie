<?php

namespace Database\Factories;

use App\Models\StaffAttendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffAttendance>
 */
class StaffAttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkedInAt = $this->faker->dateTimeBetween('-30 days', '-1 hour');

        return [
            'staff_id' => User::factory(),
            'checked_in_by_id' => User::factory(),
            'checked_in_at' => $checkedInAt,
        ];
    }
}
