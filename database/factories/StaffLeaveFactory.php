<?php

namespace Database\Factories;

use App\Enums\StaffLeaveType;
use App\Enums\Status;
use App\Models\StaffLeave;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<StaffLeave>
 */
class StaffLeaveFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = Carbon::instance($this->faker->dateTimeBetween('now', '+2 months'))->startOfDay();

        return [
            'staff_id' => User::factory(),
            'type' => $this->faker->randomElement(StaffLeaveType::cases()),
            'start_date' => $startDate,
            'end_date' => $startDate->copy()->addDays($this->faker->numberBetween(0, 5)),
            'reason' => $this->faker->sentence(),
            'status' => Status::Pending,
            'reviewed_by_id' => null,
            'reviewed_at' => null,
            'review_note' => null,
            'cancelled_by_id' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ];
    }
}
