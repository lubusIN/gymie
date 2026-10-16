<?php

namespace App\Filament\Widgets;

use App\Enums\Status;
use App\Models\Attendance;
use App\Models\Member;
use App\Support\Analytics\AnalyticsDateRange;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recent attendance and follow-up indicators for the attendance workspace.
 */
class AttendanceOverviewWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

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
        $range = ($this->pageFilters['period'] ?? null) === 'all'
            ? null
            : AnalyticsDateRange::fromFilters($this->pageFilters);
        $attendanceQuery = Attendance::query()
            ->when(
                $range,
                fn (Builder $query, AnalyticsDateRange $range): Builder => $query
                    ->whereBetween('checked_in_at', [$range->start, $range->end]),
            );
        $attendanceTotals = (clone $attendanceQuery)
            ->selectRaw('COUNT(*) as total_check_ins')
            ->selectRaw('COUNT(DISTINCT member_id) as unique_members')
            ->first();
        $totalCheckIns = (int) ($attendanceTotals?->total_check_ins ?? 0);
        $uniqueMembers = (int) ($attendanceTotals?->unique_members ?? 0);
        $membersWithoutVisits = Member::query()
            ->where('status', Status::Active->value)
            ->when(
                $range,
                fn (Builder $query, AnalyticsDateRange $range): Builder => $query
                    ->where('created_at', '<=', $range->end),
            )
            ->whereDoesntHave(
                'attendances',
                fn (Builder $query): Builder => $query->when(
                    $range,
                    fn (Builder $query, AnalyticsDateRange $range): Builder => $query
                        ->whereBetween('checked_in_at', [$range->start, $range->end]),
                ),
            )
            ->count();

        return [
            $this->stat(__('app.widgets.total_check_ins'), (string) $totalCheckIns)
                ->icon('heroicon-o-arrow-left-end-on-rectangle')
                ->color('primary'),
            $this->stat(__('app.widgets.unique_members'), (string) $uniqueMembers)
                ->icon('heroicon-o-user-group')
                ->color('success'),
            $this->stat(__('app.widgets.members_without_visits'), (string) $membersWithoutVisits)
                ->icon('heroicon-o-user-minus')
                ->color($membersWithoutVisits > 0 ? 'warning' : 'gray'),
        ];
    }

    private function stat(string $label, string $value): Stat
    {
        return Stat::make($label, $value)
            ->extraAttributes(['class' => 'gymie-workspace-overview-stat']);
    }
}
