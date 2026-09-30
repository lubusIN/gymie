<?php

namespace App\Filament\Widgets\Analytics;

use App\Services\Analytics\AnalyticsService;
use App\Support\Analytics\AnalyticsDateRange;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * Membership insights widget.
 */
class MembershipMetricsWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = -41;

    protected int|string|array $columnSpan = 'full';

    /**
     * @var int | array<string, ?int> | null
     */
    protected int|array|null $columns = 4;

    /**
     * Build an inline delta label for KPI cards.
     *
     * @return array{label: string, icon: string|null, class: string}
     */
    private function deltaInline(int $current, int $previous): array
    {
        if ($previous <= 0) {
            if ($current <= 0) {
                return [
                    'label' => '0%',
                    'icon' => 'heroicon-o-minus',
                    'class' => 'text-gray-500',
                ];
            }

            return [
                'label' => "+{$current}",
                'icon' => 'heroicon-o-arrow-trending-up',
                'class' => 'text-success-600 dark:text-success-400',
            ];
        }

        $pct = (($current - $previous) / $previous) * 100;
        $pct = round($pct, 1);

        if ($pct === 0.0) {
            return [
                'label' => '0%',
                'icon' => 'heroicon-o-minus',
                'class' => 'text-gray-500',
            ];
        }

        if ($pct > 0) {
            return [
                'label' => "+{$pct}%",
                'icon' => 'heroicon-o-arrow-trending-up',
                'class' => 'text-success-600 dark:text-success-400',
            ];
        }

        $pct = abs($pct);

        return [
            'label' => "-{$pct}%",
            'icon' => 'heroicon-o-arrow-trending-down',
            'class' => 'text-danger-600 dark:text-danger-400',
        ];
    }

    /**
     * Render a KPI value with an inline delta.
     *
     * @param  array{label: string, icon: string|null, class: string}  $delta
     */
    private function valueWithDelta(string $value, array $delta): HtmlString
    {
        $value = e($value);
        $deltaLabel = e($delta['label']);
        $deltaClass = e($delta['class']);

        $deltaIcon = null;
        if (filled($delta['icon'])) {
            $deltaIcon = Blade::render(
                '<x-filament::icon :icon="$icon" class="h-4 w-4 text-current" />',
                ['icon' => $delta['icon']],
            );
        }

        return new HtmlString(<<<HTML
<div class="flex items-baseline gap-2 my-1">
    <span>{$value}</span>
    <span class="text-sm font-semibold {$deltaClass} inline-flex items-center gap-1">{$deltaLabel}{$deltaIcon}</span>
</div>
HTML);
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $range = AnalyticsDateRange::fromFilters($this->pageFilters);
        $service = app(AnalyticsService::class);
        $metrics = $service->membershipMetrics($range);

        $previous = $service->membershipMetrics($range->previous());

        $activeDelta = $this->deltaInline($metrics['active_members'], $previous['active_members']);
        $signupDelta = $this->deltaInline($metrics['new_signups'], $previous['new_signups']);
        $renewalDelta = $this->deltaInline($metrics['renewals'], $previous['renewals']);
        $expiredDelta = $this->deltaInline($metrics['expired_not_renewed'], $previous['expired_not_renewed']);

        return [
            Stat::make(
                __('app.widgets.active_members'),
                $this->valueWithDelta((string) $metrics['active_members'], $activeDelta),
            )
                ->icon('heroicon-o-user-group')
                ->description(__('app.widgets.vs_previous_period', ['count' => (string) $previous['active_members']]))
                ->descriptionColor('gray'),
            Stat::make(
                __('app.widgets.new_members'),
                $this->valueWithDelta((string) $metrics['new_signups'], $signupDelta),
            )
                ->icon('heroicon-o-user-plus')
                ->description(__('app.widgets.vs_previous_period', ['count' => (string) $previous['new_signups']]))
                ->descriptionColor('gray'),
            Stat::make(
                __('app.widgets.renewals'),
                $this->valueWithDelta((string) $metrics['renewals'], $renewalDelta),
            )
                ->icon('heroicon-o-arrow-path')
                ->description(__('app.widgets.vs_previous_period', ['count' => (string) $previous['renewals']]))
                ->descriptionColor('gray'),
            Stat::make(
                __('app.widgets.expired_not_renewed'),
                $this->valueWithDelta((string) $metrics['expired_not_renewed'], $expiredDelta),
            )
                ->icon('heroicon-o-x-circle')
                ->description(__('app.widgets.vs_previous_period', ['count' => (string) $previous['expired_not_renewed']]))
                ->descriptionColor('gray'),
        ];
    }
}
