<?php

namespace App\Support\Filament;

use App\Helpers\Helpers;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

final class MetricTrend
{
    /**
     * Build a percentage comparison with the preceding date range.
     *
     * @return array{label: string, icon: string, class: string}
     */
    public static function percentage(float $current, float $previous): array
    {
        if ($current === $previous) {
            return [
                'label' => '0%',
                'icon' => 'heroicon-o-minus',
                'class' => 'text-gray-500',
            ];
        }

        if ($previous <= 0) {
            return [
                'label' => '+100%',
                'icon' => 'heroicon-o-arrow-trending-up',
                'class' => 'text-success-600 dark:text-success-400',
            ];
        }

        $percentage = round((($current - $previous) / $previous) * 100, 1);

        if ($percentage > 0) {
            return [
                'label' => "+{$percentage}%",
                'icon' => 'heroicon-o-arrow-trending-up',
                'class' => 'text-success-600 dark:text-success-400',
            ];
        }

        return [
            'label' => $percentage.'%',
            'icon' => 'heroicon-o-arrow-trending-down',
            'class' => 'text-danger-600 dark:text-danger-400',
        ];
    }

    /** Create a currency card with an optional previous-period comparison. */
    public static function currencyStat(
        string $label,
        float $current,
        ?float $previous,
        string $icon,
        ?string $color = null,
    ): Stat {
        $stat = Stat::make($label, Helpers::formatCurrency($current))
            ->icon($icon)
            ->extraAttributes(['class' => 'gymie-workspace-overview-stat'])
            ->color($color);

        if ($previous === null) {
            return $stat;
        }

        return $stat
            ->value(self::inlineValue(
                Helpers::formatCurrency($current),
                self::percentage($current, $previous),
            ))
            ->description(__('app.widgets.vs_previous_period', [
                'count' => Helpers::formatCurrency($previous),
            ]))
            ->descriptionColor('gray');
    }

    /**
     * Render a metric value with its trend on the same baseline.
     *
     * @param  array{label: string, icon: string|null, class: string}  $trend
     */
    public static function inlineValue(string $value, array $trend): HtmlString
    {
        $value = e($value);
        $trendLabel = e($trend['label']);
        $trendClass = e($trend['class']);

        $trendIcon = null;

        if (filled($trend['icon'])) {
            $trendIcon = Blade::render(
                '<x-filament::icon :icon="$icon" class="size-4 text-current" />',
                ['icon' => $trend['icon']],
            );
        }

        return new HtmlString(<<<HTML
<div class="my-1 flex items-baseline gap-2">
    <span>{$value}</span>
    <span class="inline-flex items-center gap-1 text-sm font-semibold {$trendClass}">{$trendLabel}{$trendIcon}</span>
</div>
HTML);
    }
}
