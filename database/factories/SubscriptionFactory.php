<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $timezone = config('app.timezone');
        $today = CarbonImmutable::today($timezone);
        $expiringEnd = $today->addDays(7);

        $bucket = $this->faker->randomElement(['expired', 'expiring', 'ongoing', 'upcoming']);

        [$startDate, $endDate] = match ($bucket) {
            'expired' => [
                $today->subDays($this->faker->numberBetween(45, 240)),
                $today->subDays($this->faker->numberBetween(1, 30)),
            ],
            'expiring' => [
                $today->subDays($this->faker->numberBetween(5, 120)),
                CarbonImmutable::instance($this->faker->dateTimeBetween($today->toDateString(), $expiringEnd->toDateString(), $timezone))->startOfDay(),
            ],
            'upcoming' => [
                $today->addDays($this->faker->numberBetween(1, 30)),
                $today->addDays($this->faker->numberBetween(45, 365)),
            ],
            default => [
                $today->subDays($this->faker->numberBetween(10, 180)),
                $today->addDays($this->faker->numberBetween(10, 365)),
            ],
        };

        $status = match (true) {
            $startDate->greaterThan($today) => 'upcoming',
            $endDate->lessThan($today) => 'expired',
            $endDate->lessThanOrEqualTo($expiringEnd) => 'expiring',
            default => 'ongoing',
        };

        return [
            'member_id' => Member::query()->inRandomOrder()->value('id') ?? Member::factory(),
            'plan_id' => Plan::query()->inRandomOrder()->value('id') ?? Plan::factory(),
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'status' => $status,
        ];
    }

    public function current(): static
    {
        $today = CarbonImmutable::today(config('app.timezone'));

        return $this->state(fn (): array => [
            'start_date' => $today->subMonth()->toDateString(),
            'end_date' => $today->addMonth()->toDateString(),
            'status' => 'ongoing',
        ]);
    }
}
