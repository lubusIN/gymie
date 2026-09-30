<?php

namespace App\Filament\Widgets;

use App\Enums\Status;
use App\Models\Attendance;
use App\Models\Member;
use App\Support\AppConfig;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Recent attendance and follow-up indicators for the attendance workspace.
 */
class AttendanceOverviewWidget extends StatsOverviewWidget
{
    /** @var int|string|array<string, int|null> */
    protected int|string|array $columnSpan = 'full';

    /** @var int|array<string, ?int>|null */
    protected int|array|null $columns = 3;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Attendance') ?? false;
    }

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $today = Carbon::today(AppConfig::timezone());
        $todayVisits = Attendance::query()
            ->where('visited_on', $today->toDateString())
            ->count();
        $lastSevenDays = Attendance::query()
            ->where('visited_on', '>=', $today->copy()->subDays(6)->toDateString())
            ->count();
        $membersNeedingFollowUp = Member::query()
            ->where('status', Status::Active->value)
            ->whereDoesntHave(
                'attendances',
                fn (Builder $query): Builder => $query->where(
                    'visited_on',
                    '>=',
                    $today->copy()->subDays(13)->toDateString(),
                ),
            )
            ->count();

        return [
            Stat::make(__('app.widgets.today_check_ins'), $todayVisits)
                ->icon('heroicon-o-arrow-left-end-on-rectangle')
                ->color('primary'),
            Stat::make(__('app.widgets.visits_last_7_days'), $lastSevenDays)
                ->icon('heroicon-o-calendar-days')
                ->color('success'),
            Stat::make(__('app.widgets.members_needing_follow_up'), $membersNeedingFollowUp)
                ->icon('heroicon-o-user-minus')
                ->color($membersNeedingFollowUp > 0 ? 'warning' : 'gray'),
        ];
    }
}
