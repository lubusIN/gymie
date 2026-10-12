<?php

namespace App\Console\Commands;

use App\Enums\CommunicationTemplateKey;
use App\Enums\Status;
use App\Models\CommunicationDelivery;
use App\Models\Member;
use App\Models\Subscription;
use App\Services\Communication\CommunicationAutomationService;
use App\Services\Communication\CommunicationService;
use App\Support\AppConfig;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

#[Signature('gymie:inactive-members')]
#[Description('Queue engagement emails for active members who have stopped attending')]
class SendInactiveMemberReminders extends Command
{
    /** @var list<int> */
    private const REMINDER_DAYS = [7, 14, 30];

    /** Queue each 7, 14, or 30-day reminder at most once per business day. */
    public function handle(
        CommunicationAutomationService $automationService,
        CommunicationService $communicationService,
    ): int {
        if (! $automationService->shouldSend(CommunicationTemplateKey::MemberInactive)) {
            $this->info('Inactive-member communication is disabled.');

            return self::SUCCESS;
        }

        $today = Carbon::today(AppConfig::timezone());
        $queuedCount = 0;

        Member::query()
            ->where('status', Status::Active->value)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereHas('subscriptions', fn ($query) => $query->currentAt($today))
            ->with([
                'subscriptions' => fn ($query) => $query->currentAt($today)
                    ->orderByDesc('end_date')
                    ->orderByDesc('id'),
            ])
            ->withMax('attendances as last_visited_on', 'visited_on')
            ->chunkById(
                500,
                /** @param Collection<int, Member> $members */
                function (Collection $members) use ($communicationService, $today, &$queuedCount): void {
                    foreach ($members as $member) {
                        $subscription = $member->subscriptions->first();

                        if (! $subscription instanceof Subscription) {
                            continue;
                        }

                        $lastVisitedOn = $member->getAttribute('last_visited_on');
                        $lastVisit = $lastVisitedOn
                            ? Carbon::parse((string) $lastVisitedOn, AppConfig::timezone())
                            : null;
                        $subscriptionStart = $subscription->start_date;
                        $inactiveSince = $lastVisit !== null && $subscriptionStart !== null && $lastVisit->isAfter($subscriptionStart)
                            ? $lastVisit
                            : $subscriptionStart;

                        if ($inactiveSince === null || $inactiveSince->isFuture()) {
                            continue;
                        }

                        $inactiveDays = (int) $inactiveSince->startOfDay()->diffInDays($today);

                        if (! in_array($inactiveDays, self::REMINDER_DAYS, true) || $this->alreadyQueuedToday($member, $today)) {
                            continue;
                        }

                        $communicationService->queue(
                            templateKey: CommunicationTemplateKey::MemberInactive,
                            recipient: (string) $member->email,
                            variables: [
                                'member_name' => $member->name,
                                'member_code' => $member->code,
                                'inactive_days' => $inactiveDays,
                                'last_visit_date' => $lastVisitedOn
                                    ? Carbon::parse((string) $lastVisitedOn)->format('M j, Y')
                                    : '',
                            ],
                            related: $member,
                        );
                        $queuedCount++;
                    }
                },
            );

        $this->info("{$queuedCount} inactive-member reminder(s) queued.");

        return self::SUCCESS;
    }

    /** Prevent a repeated command run from sending the same milestone twice. */
    private function alreadyQueuedToday(Member $member, Carbon $today): bool
    {
        return CommunicationDelivery::query()
            ->where('related_type', $member->getMorphClass())
            ->where('related_id', $member->getKey())
            ->whereHas('template', fn ($query) => $query
                ->where('event', CommunicationTemplateKey::MemberInactive->value))
            ->whereBetween('created_at', [
                $today->copy()->startOfDay()->utc(),
                $today->copy()->endOfDay()->utc(),
            ])
            ->exists();
    }
}
