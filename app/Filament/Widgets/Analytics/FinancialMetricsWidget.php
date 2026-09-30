<?php

namespace App\Filament\Widgets\Analytics;

use App\Helpers\Helpers;
use App\Services\Analytics\AnalyticsService;
use App\Support\Analytics\AnalyticsDateRange;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Financial overview widget for key revenue and expense KPIs.
 */
class FinancialMetricsWidget extends Widget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = -40;

    /** @var view-string */
    protected string $view = 'filament.widgets.analytics.financial-metrics';

    /**
     * @var int | string | array<string, int | null>
     */
    protected int|string|array $columnSpan = [
        'default' => 1,
        'md' => 2,
    ];

    /**
     * Build the compact comparison shown beside a financial metric.
     *
     * @return array{label: string, icon: string, color: 'gray'|'success'|'danger'}
     */
    private function trend(float $current, float $previous): array
    {
        if ($previous <= 0) {
            if ($current <= 0) {
                return [
                    'label' => '0%',
                    'icon' => 'heroicon-o-minus',
                    'color' => 'gray',
                ];
            }

            return [
                'label' => '+'.Helpers::formatCurrency($current),
                'icon' => 'heroicon-o-arrow-trending-up',
                'color' => 'success',
            ];
        }

        $percentage = round((($current - $previous) / $previous) * 100, 1);

        if ($percentage === 0.0) {
            return [
                'label' => '0%',
                'icon' => 'heroicon-o-minus',
                'color' => 'gray',
            ];
        }

        if ($percentage > 0) {
            return [
                'label' => "+{$percentage}%",
                'icon' => 'heroicon-o-arrow-trending-up',
                'color' => 'success',
            ];
        }

        return [
            'label' => '-'.abs($percentage).'%',
            'icon' => 'heroicon-o-arrow-trending-down',
            'color' => 'danger',
        ];
    }

    /**
     * @return array{
     *     heading: string,
     *     metrics: array<int, array{
     *         label: string,
     *         value: string,
     *         icon: string,
     *         trend: array{label: string, icon: string, color: 'gray'|'success'|'danger'},
     *         trendTitle: string
     *     }>
     * }
     */
    protected function getViewData(): array
    {
        $range = AnalyticsDateRange::fromFilters($this->pageFilters);
        $service = app(AnalyticsService::class);
        $metrics = $service->financialMetrics($range);

        $previous = $service->financialMetrics($range->previous());

        $definitions = [
            ['key' => 'net_revenue', 'label' => __('app.widgets.net_revenue'), 'icon' => 'heroicon-o-banknotes'],
            ['key' => 'collected', 'label' => __('app.widgets.total_collected'), 'icon' => 'heroicon-o-arrow-down-tray'],
            ['key' => 'outstanding', 'label' => __('app.widgets.outstanding_payments'), 'icon' => 'heroicon-o-clock'],
            ['key' => 'profit', 'label' => __('app.widgets.profit'), 'icon' => 'heroicon-o-chart-bar-square'],
        ];

        return [
            'heading' => __('app.widgets.financial_overview'),
            'metrics' => array_map(fn (array $definition): array => [
                'label' => $definition['label'],
                'value' => Helpers::formatCurrency($metrics[$definition['key']]),
                'icon' => $definition['icon'],
                'trend' => $this->trend(
                    $metrics[$definition['key']],
                    $previous[$definition['key']],
                ),
                'trendTitle' => __('app.widgets.vs_previous_period', [
                    'count' => Helpers::formatCurrency($previous[$definition['key']]),
                ]),
            ], $definitions),
        ];
    }
}
