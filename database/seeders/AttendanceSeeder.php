<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Subscription;
use App\Models\User;
use App\Support\AppConfig;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seed recent attendance against subscriptions that were valid in the demo window.
 */
class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now(AppConfig::timezone());
        $windowStart = $now->copy()->subDays(30);
        $recordedBy = User::query()->first();

        if ($recordedBy === null) {
            return;
        }

        Subscription::query()
            ->where('start_date', '<=', $now->toDateString())
            ->where('end_date', '>=', $windowStart->toDateString())
            ->eachById(function (Subscription $subscription) use ($now, $windowStart, $recordedBy): void {
                $periodStart = Carbon::parse($subscription->start_date->toDateString(), AppConfig::timezone())
                    ->max($windowStart);
                $periodEnd = Carbon::parse($subscription->end_date->toDateString(), AppConfig::timezone())
                    ->endOfDay()
                    ->min($now->copy()->subHour());

                if ($periodStart->isAfter($periodEnd)) {
                    return;
                }

                $availableDays = $periodStart->copy()->startOfDay()
                    ->diffInDays($periodEnd->copy()->startOfDay()) + 1;
                $visitCount = min(3, (int) $availableDays);

                foreach (range(0, $visitCount - 1) as $dayOffset) {
                    $checkedInAt = $periodEnd->copy()
                        ->startOfDay()
                        ->subDays($dayOffset)
                        ->addHours(fake()->numberBetween(6, 20))
                        ->max($periodStart)
                        ->min($periodEnd);

                    $alreadySeeded = Attendance::query()
                        ->where('member_id', $subscription->member_id)
                        ->whereDate('visited_on', $checkedInAt->toDateString())
                        ->exists();

                    if ($alreadySeeded) {
                        continue;
                    }

                    Attendance::query()->create([
                        'subscription_id' => $subscription->getKey(),
                        'member_id' => $subscription->member_id,
                        'checked_in_by_id' => $recordedBy->getKey(),
                        'checked_in_at' => $checkedInAt,
                    ]);
                }
            });
    }
}
