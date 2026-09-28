<?php

namespace Database\Factories;

use App\Enums\ExpensePaymentMethod;
use App\Enums\Status;
use App\Helpers\Helpers;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = Carbon::instance($this->faker->dateTimeBetween('-6 months', 'now'))->startOfDay();

        $status = $this->faker->randomElement([
            Status::Pending->value,
            Status::Paid->value,
            Status::Overdue->value,
            Status::Cancelled->value,
        ]);

        $paidAt = null;
        if ($status === Status::Paid->value) {
            $paidAt = Carbon::instance($this->faker->dateTimeBetween($date, 'now'));
        }

        $categoryKeys = array_keys(Helpers::getExpenseCategoryOptions());
        $category = $this->faker->randomElement($categoryKeys ?: ['other']);

        return [
            'name' => $this->faker->sentence(3),
            'amount' => $this->faker->randomFloat(2, 10, 5000),
            'date' => $date->toDateString(),
            'due_date' => $this->faker->boolean(60)
                ? $date->copy()->addDays($this->faker->numberBetween(0, 30))->toDateString()
                : null,
            'paid_at' => $paidAt?->format('Y-m-d H:i:s'),
            'category' => $category,
            'status' => $status,
            'vendor' => $this->faker->boolean(75) ? $this->faker->company() : null,
            'payment_method' => $status === Status::Paid->value
                ? $this->faker->randomElement(ExpensePaymentMethod::cases())->value
                : null,
            'payment_reference' => $status === Status::Paid->value && $this->faker->boolean()
                ? $this->faker->bothify('PAY-########')
                : null,
            'notes' => $this->faker->boolean(55) ? $this->faker->sentence(12) : null,
        ];
    }
}
