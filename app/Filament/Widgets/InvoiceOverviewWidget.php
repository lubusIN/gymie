<?php

namespace App\Filament\Widgets;

use App\Enums\Status;
use App\Services\Analytics\AnalyticsService;
use App\Support\Analytics\AnalyticsDateRange;
use App\Support\Filament\MetricTrend;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Invoice amounts for the invoice workspace. */
class InvoiceOverviewWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    /** @var int|string|array<string, int|null> */
    protected int|string|array $columnSpan = 'full';

    /** @var int|array<string, ?int>|null */
    protected int|array|null $columns = 4;

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $service = app(AnalyticsService::class);
        $allDates = ($this->pageFilters['period'] ?? null) === 'all';
        $range = $allDates ? null : AnalyticsDateRange::fromFilters($this->pageFilters);
        $totals = $service->invoiceStatusTotals($range);
        $previous = $range ? $service->invoiceStatusTotals($range->previous()) : null;

        return [
            MetricTrend::currencyStat(
                __('app.summaries.total_invoiced'),
                $totals['total'],
                $previous['total'] ?? null,
                'heroicon-o-document-text',
            ),
            MetricTrend::currencyStat(
                __('app.summaries.paid'),
                $totals['paid'],
                $previous['paid'] ?? null,
                'heroicon-o-banknotes',
                'success',
            ),
            MetricTrend::currencyStat(
                __('app.summaries.outstanding'),
                $totals['outstanding'],
                $previous['outstanding'] ?? null,
                'heroicon-o-clock',
                'warning',
            ),
            MetricTrend::currencyStat(
                Status::Overdue->getLabel(),
                $totals['overdue'],
                $previous['overdue'] ?? null,
                'heroicon-o-exclamation-triangle',
                'danger',
            ),
        ];
    }
}
