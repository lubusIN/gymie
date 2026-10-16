<?php

namespace App\Filament\Widgets\Analytics;

use App\Helpers\Helpers;
use App\Services\Analytics\AnalyticsService;
use App\Support\Analytics\AnalyticsDateRange;
use Filament\Support\Facades\FilamentColor;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Spending overview card showing expense category breakdown for the selected period.
 *
 * This widget is intentionally not a Chart.js widget, so it can render a "stacked bar"
 * and a compact category list (similar to common analytics dashboards).
 */
class ExpenseCategoriesDoughnutChartWidget extends Widget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = -39;

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.analytics.expense-spending-overview';

    /**
     * @var int | string | array<string, int | null>
     */
    protected int|string|array $columnSpan = [
        'default' => 1,
        'md' => 2,
    ];

    /**
     * Resolve a registered Filament color shade (or return a fallback).
     *
     * @param  array<int | string, string | int> | null  $palette
     */
    private function colorShade(?array $palette, int|string $shade, string $fallback): string
    {
        if (is_array($palette)) {
            $value = $palette[$shade] ?? null;
            if (is_string($value) && filled($value)) {
                return $value;
            }

            $fallbackValue = $palette[500] ?? $palette['500'] ?? null;
            if (is_string($fallbackValue) && filled($fallbackValue)) {
                return $fallbackValue;
            }
        }

        return $fallback;
    }

    /**
     * Segment color palette for expense categories.
     *
     * Colors are resolved from the panel color configuration registered in
     * `app/Providers/Filament/AdminPanelProvider.php`.
     *
     * @return array<int, string>
     */
    private function segmentPalette(): array
    {
        $primary = FilamentColor::getColor('primary');

        return [
            $this->colorShade($primary, 600, '#042f2e'),
            $this->colorShade($primary, 500, '#084543'),
            $this->colorShade($primary, 400, '#0e5c5a'),
            $this->colorShade($primary, 300, '#157573'),
            $this->colorShade($primary, 200, '#1c908d'),
            $this->colorShade($primary, 100, '#24adaa'),
        ];
    }

    /**
     * @return Collection<int, array{label: string, total: float, flex: float, color: string}>
     */
    private function buildSegments(AnalyticsDateRange $range): Collection
    {
        $rows = app(AnalyticsService::class)->expenseCategoryBreakdownForChart($range, 5);

        $palette = $this->segmentPalette();

        return $rows
            ->sortByDesc('total')
            ->values()
            ->map(function (array $row, int $index) use ($palette): array {
                $category = (string) $row['category'];
                $resolvedLabel = Helpers::getExpenseCategoryLabel($category);
                $label = (string) ($category === 'Other'
                    ? __('app.analytics.other')
                    : (is_string($resolvedLabel) && filled($resolvedLabel) ? $resolvedLabel : $category));

                return [
                    'label' => $label,
                    'total' => (float) $row['total'],
                    'flex' => (float) max((float) $row['total'], 0),
                    'color' => $palette[$index] ?? $palette[max(array_key_last($palette) ?? 0, 0)],
                ];
            })
            ->filter(fn (array $segment): bool => $segment['flex'] > 0)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $range = AnalyticsDateRange::fromFilters($this->pageFilters);

        $service = app(AnalyticsService::class);
        $expenses = (float) $service->financialMetrics($range)['expenses'];

        $segments = $this->buildSegments($range);

        return [
            'heading' => __('app.widgets.total_expense'),
            'totalExpense' => Helpers::formatCurrency($expenses),
            'segments' => $segments,
        ];
    }
}
