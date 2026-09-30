<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Attendance> */
class AttendanceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $checkedInAt = $this->faker->dateTimeBetween('-30 days', '-1 hour');

        return [
            'member_id' => Member::factory(),
            'checked_in_by_id' => User::factory(),
            'checked_in_at' => $checkedInAt,
        ];
    }
}
