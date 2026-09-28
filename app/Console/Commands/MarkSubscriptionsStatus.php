<?php

namespace App\Console\Commands;

use App\Enums\Status;
use App\Helpers\Helpers;
use App\Models\Subscription;
use App\Models\User;
use App\Support\AppConfig;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class MarkSubscriptionsStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gymie:subscriptions
                            {--mark-expired : Mark expired subscriptions}
                            {--mark-expiring : Mark subscriptions expiring within the configured window}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark subscriptions as expiring or expired';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $timezone = AppConfig::timezone();
        $today = Carbon::today($timezone);
        $expiringDays = Helpers::getSubscriptionExpiringDays();
        $expiringThreshold = $today->copy()->addDays($expiringDays);

        $summary = [];

        $runExpiredOnly = (bool) $this->option('mark-expired');
        $runExpiringOnly = (bool) $this->option('mark-expiring');
        $runAll = ! $runExpiredOnly && ! $runExpiringOnly;

        if ($runAll || $runExpiredOnly) {
            $expiredCount = $this->updateStatusInChunks(Subscription::query()
                ->where('end_date', '<', $today->toDateString())
                ->whereNotIn('status', ['expired', 'renewed'])
                ->whereDoesntHave('renewals'), Status::Expired);

            if ($expiredCount > 0) {
                $summary[] = "{$expiredCount} expired";
            }
        }

        if ($runAll || $runExpiredOnly) {
            $renewedCount = $this->updateStatusInChunks(Subscription::query()
                ->where('end_date', '<', $today->toDateString())
                ->where('status', '!=', 'renewed')
                ->whereHas('renewals'), Status::Renewed);

            if ($renewedCount > 0) {
                $summary[] = "{$renewedCount} renewed";
            }
        }

        if ($runAll) {
            $upcomingCount = $this->updateStatusInChunks(Subscription::query()
                ->where('start_date', '>', $today->toDateString())
                ->where('status', '!=', 'renewed')
                ->where('status', '!=', 'upcoming'), Status::Upcoming);

            if ($upcomingCount > 0) {
                $summary[] = "{$upcomingCount} upcoming";
            }
        }

        if ($runAll || $runExpiringOnly) {
            $expiringCount = $this->updateStatusInChunks(Subscription::query()
                ->where('start_date', '<=', $today->toDateString())
                ->whereBetween('end_date', [$today->toDateString(), $expiringThreshold->toDateString()])
                ->where('status', '!=', 'renewed')
                ->where('status', '!=', 'expiring')
                ->where('status', '!=', 'expired'), Status::Expiring);

            if ($expiringCount > 0) {
                $summary[] = "{$expiringCount} expiring (≤ {$expiringDays} days)";
            }
        }

        if ($runAll) {
            $ongoingCount = $this->updateStatusInChunks(Subscription::query()
                ->where('start_date', '<=', $today->toDateString())
                ->where('end_date', '>', $expiringThreshold->toDateString())
                ->whereNotIn('status', ['ongoing', 'expired', 'renewed']), Status::Ongoing);

            if ($ongoingCount > 0) {
                $summary[] = "{$ongoingCount} ongoing";
            }
        }

        if (empty($summary)) {
            $this->info('No subscription statuses needed updating.');

            return self::SUCCESS;
        }

        foreach ($summary as $line) {
            $this->info("• {$line}");
        }

        $admin = User::role('super_admin')->first();
        if ($admin) {
            Notification::make()
                ->title(__('app.notifications.subscription_status_update_title'))
                ->body(__('app.notifications.subscription_status_update_body', ['summary' => implode(', ', $summary)]))
                ->info()
                ->sendToDatabase($admin);
        }

        return self::SUCCESS;
    }

    /**
     * @param  Builder<Subscription>  $query
     */
    private function updateStatusInChunks(Builder $query, Status $status): int
    {
        $updatedCount = 0;
        $query
            ->with([
                'member:id',
                'plan:id,name',
            ])
            ->chunkById(
                500,
                /** @param Collection<int, Subscription> $subscriptions */
                function (Collection $subscriptions) use (&$updatedCount, $status): void {
                    foreach ($subscriptions as $subscription) {
                        if ($subscription->update(['status' => $status])) {
                            $updatedCount++;
                        }
                    }
                },
            );

        return $updatedCount;
    }
}
