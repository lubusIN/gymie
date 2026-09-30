<?php

namespace App\Filament\Widgets\Analytics;

use App\Helpers\Helpers;
use App\Services\Analytics\AnalyticsService;
use App\Support\Analytics\AnalyticsDateRange;
use App\Support\AppConfig;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Cashflow trend chart (net collected vs expenses) for the selected date range.
 *
 * - Net collected is derived from `invoice_transactions` (payments - refunds).
 * - Expenses are derived from `expenses.amount`.
 */
class CashflowTrendChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = -38;

    protected ?string $maxHeight = '400px';

    public ?string $filter = '7days';

    /**
     * @var int | string | array<string, int | null>
     */
    protected int|string|array $columnSpan = [
        'default' => 1,
        'md' => 4,
    ];

    protected function getType(): string
    {
        return 'line';
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('app.widgets.cashflow');
    }

    /**
     * Quick range filters for this chart.
     *
     * This is intentionally separate from the dashboard header range selector
     * so you can keep KPIs on a short window (like last 7 days) while looking
     * at cashflow on a longer window (like last 6 months).
     */
    protected function getFilters(): ?array
    {
        return [
            '7days' => __('app.analytics.ranges.7days'),
            '30days' => __('app.analytics.ranges.30days'),
            'quarter' => __('app.analytics.ranges.quarter'),
            '6months' => __('app.analytics.ranges.6months'),
            'ytd' => __('app.analytics.ranges.ytd'),
        ];
    }

    /**
     * Resolve the date range used for this chart.
     */
    private function resolveRange(): AnalyticsDateRange
    {
        $rangeKey = $this->filter ?? '7days';

        $today = CarbonImmutable::today(AppConfig::timezone());

        [$start, $end] = match ($rangeKey) {
            '30days' => [$today->subDays(29), $today],
            'quarter' => [$today->startOfQuarter(), $today],
            '6months' => [$today->startOfMonth()->subMonthsNoOverflow(5), $today],
            'ytd' => [$today->startOfYear(), $today],
            default => [$today->subDays(6), $today],
        };

        return new AnalyticsDateRange(
            $start->startOfDay(),
            $end->endOfDay(),
        );
    }

    /**
     * Chart.js options.
     *
     * - Formats y-axis and tooltips with the configured currency symbol.
     * - Ensures tooltip dataset markers match the dataset color.
     * - Adds spacing between tooltip items for readability.
     * - Adds a bit more vertical breathing room via padding and axis styling.
     * - Omits the year from compact axis labels while retaining it in tooltips.
     */
    protected function getOptions(): array|RawJs|null
    {
        $currencySymbol = Helpers::getCurrencySymbol();
        $currencySymbol = str_replace("'", "\\'", $currencySymbol);

        return RawJs::make(<<<JS
{
    layout: {
        padding: { top: 14, right: 8, bottom: 22, left: 8 },
    },
    interaction: {
        mode: 'index',
        intersect: false,
    },
    scales: {
        x: {
            ticks: {
                maxRotation: 0,
                callback: function (value) {
                    const label = this.getLabelForValue(value);

                    return typeof label === 'string'
                        ? label.replace(/\s+\d{4}$/, '')
                        : label;
                },
            },
            grid: { display: false },
        },
        y: {
            beginAtZero: true,
            grace: '10%',
            grid: {
                color: 'rgba(148, 163, 184, 0.22)',
                drawTicks: false,
            },
            ticks: {
                padding: 8,
                callback: (value) => '{$currencySymbol}' + Number(value).toLocaleString(),
            },
        },
    },
    plugins: {
        tooltip: {
            padding: 12,
            boxPadding: 6,
            bodySpacing: 8,
            titleMarginBottom: 10,
            usePointStyle: true,
            callbacks: {
                labelColor: (context) => {
                    const color = context.dataset?.borderColor ?? 'rgba(148, 163, 184, 1)';
                    return { borderColor: color, backgroundColor: color };
                },
                label: (context) => {
                    const label = context.dataset?.label ?? '';
                    const value = context.parsed?.y ?? 0;
                    return label + ': ' + '{$currencySymbol}' + Number(value).toLocaleString();
                },
            },
        },
        legend: {
            position: 'bottom',
            align: 'center',
            labels: {
                boxWidth: 10,
                boxHeight: 10,
                padding: 28,
            },
        },
    },
}
JS);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $range = $this->resolveRange();
        $rangeKey = $this->filter ?? '7days';
        $useDaily = in_array($rangeKey, ['7days', '30days'], true);

        $service = app(AnalyticsService::class);

        $collectedTrend = $useDaily
            ? $service->collectedTrendByDate($range)
            : $service->collectedTrendByMonth($range);

        $expenseTrend = $useDaily
            ? $service->expenseTrendByDate($range)
            : $service->expenseTrendByMonth($range);

        $labels = [];
        $collected = [];
        $expenses = [];

        if ($useDaily) {
            $period = CarbonPeriod::create($range->start->toDateString(), $range->end->toDateString());

            foreach ($period as $date) {
                if (! $date instanceof CarbonInterface) {
                    continue;
                }

                $dayKey = $date->toDateString();

                $labels[] = CarbonImmutable::parse($date)->translatedFormat('j M Y');
                $collected[] = (float) ($collectedTrend[$dayKey] ?? 0);
                $expenses[] = (float) ($expenseTrend[$dayKey] ?? 0);
            }
        } else {
            $start = $range->start->startOfMonth();
            $end = $range->end->startOfMonth();

            $period = CarbonPeriod::create($start, '1 month', $end);
            foreach ($period as $date) {
                if (! $date instanceof CarbonInterface) {
                    continue;
                }

                $monthKey = $date->format('Y-m');

                $labels[] = CarbonImmutable::parse($date)->translatedFormat('M Y');
                $collected[] = (float) ($collectedTrend[$monthKey] ?? 0);
                $expenses[] = (float) ($expenseTrend[$monthKey] ?? 0);
            }
        }

        return [
            'datasets' => [
                [
                    'label' => __('app.analytics.collected'),
                    'data' => $collected,
                    'borderColor' => 'oklch(87.2% 0.01 258.338)',
                    'backgroundColor' => 'rgba(0, 0, 0, 0)',
                    'tension' => 0.35,
                    'fill' => true,
                    'borderWidth' => 2,
                    'borderRadius' => 6,
                    'pointHoverRadius' => 4,
                    'pointBackgroundColor' => 'oklch(87.2% 0.01 258.338)',
                    'pointBorderColor' => 'oklch(87.2% 0.01 258.338)',
                    'pointHoverBackgroundColor' => 'oklch(87.2% 0.01 258.338)',
                    'pointHoverBorderColor' => 'oklch(87.2% 0.01 258.338)',
                ],
                [
                    'label' => __('app.analytics.expenses'),
                    'data' => $expenses,
                    'borderColor' => '#093e3d',
                    'backgroundColor' => 'rgba(0, 0, 0, 0)',
                    'tension' => 0.35,
                    'fill' => true,
                    'borderWidth' => 2,
                    'borderRadius' => 6,
                    'pointHoverRadius' => 4,
                    'pointBackgroundColor' => '#093e3d',
                    'pointBorderColor' => '#093e3d',
                    'pointHoverBackgroundColor' => '#093e3d',
                    'pointHoverBorderColor' => '#093e3d',
                ],
            ],
            'labels' => $labels,
        ];
    }
}
