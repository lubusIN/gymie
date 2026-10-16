<?php

namespace App\Filament\Widgets;

use App\Services\Analytics\AnalyticsService;
use App\Support\Analytics\AnalyticsDateRange;
use App\Support\Filament\MetricTrend;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Expense status totals for the expense workspace.
 */
class ExpenseOverviewWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    /**
     * @var int | string | array<string, int | null>
     */
    protected int|string|array $columnSpan = 'full';

    /**
     * @var int | array<string, ?int> | null
     */
    protected int|array|null $columns = 4;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $allDates = ($this->pageFilters['period'] ?? null) === 'all';
        $range = $allDates ? null : AnalyticsDateRange::fromFilters($this->pageFilters);
        $service = app(AnalyticsService::class);
        $totals = $service->expenseStatusTotals($range);
        $previous = $range ? $service->expenseStatusTotals($range->previous()) : null;

        return [
            MetricTrend::currencyStat(
                __('app.widgets.total_expense'),
                $totals['total'],
                $previous['total'] ?? null,
                'heroicon-o-banknotes',
            ),
            MetricTrend::currencyStat(
                __('app.widgets.paid_expenses'),
                $totals['paid'],
                $previous['paid'] ?? null,
                'heroicon-o-check-circle',
                'success',
            ),
            MetricTrend::currencyStat(
                __('app.widgets.pending_expenses'),
                $totals['pending'],
                $previous['pending'] ?? null,
                'heroicon-o-clock',
                'warning',
            ),
            MetricTrend::currencyStat(
                __('app.widgets.overdue_expenses'),
                $totals['overdue'],
                $previous['overdue'] ?? null,
                'heroicon-o-exclamation-triangle',
                'danger',
            ),
        ];
    }
}
