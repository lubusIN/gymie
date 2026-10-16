<?php

namespace App\Support\Filament;

use App\Support\Analytics\AnalyticsDateRange;
use App\Support\AppConfig;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

trait HasDateRangeFilters
{
    /** Configure the shared preset and custom date-range controls. */
    protected function configureDateRangeFilters(Schema $schema): Schema
    {
        return $schema
            ->inline()
            ->statePath('filters')
            ->extraAttributes([
                'class' => 'flex flex-wrap items-center justify-end gap-2',
            ])
            ->components([
                Select::make('period')
                    ->hiddenLabel()
                    ->prefixIcon('heroicon-o-calendar-days')
                    ->options($this->dateRangePeriodOptions())
                    ->default($this->defaultDateRangePeriod())
                    ->live()
                    ->afterStateUpdated(function (mixed $state): void {
                        if ($this->supportsAllDateRange() && blank($state)) {
                            $this->setPeriod('all');

                            return;
                        }

                        if (in_array($state, ['all', 'today', '7days', '30days', 'month', 'quarter', 'year', 'custom'], true)) {
                            $this->setPeriod($state);
                        }
                    })
                    ->grow(false)
                    ->extraFieldWrapperAttributes([
                        'class' => 'w-full sm:w-56',
                    ]),
                DatePicker::make('startDate')
                    ->hiddenLabel()
                    ->placeholder(__('app.dashboard.filters.start'))
                    ->hidden(fn (Get $get): bool => $get('period') !== 'custom')
                    ->live()
                    ->grow(false)
                    ->extraFieldWrapperAttributes([
                        'class' => 'w-full sm:w-40',
                    ]),
                DatePicker::make('endDate')
                    ->hiddenLabel()
                    ->placeholder(__('app.dashboard.filters.end'))
                    ->hidden(fn (Get $get): bool => $get('period') !== 'custom')
                    ->live()
                    ->grow(false)
                    ->extraFieldWrapperAttributes([
                        'class' => 'w-full sm:w-40',
                    ]),
            ]);
    }

    /** Apply the selected inclusive range to a date-only model column. */
    protected function applyDateRangeToQuery(Builder $query, string $column = 'date'): Builder
    {
        if (($this->filters['period'] ?? null) === 'all') {
            return $query;
        }

        $range = AnalyticsDateRange::fromFilters($this->filters);

        return $query->whereBetween($column, [$range->start, $range->end]);
    }

    /** Initialize a valid range without replacing a persisted selection. */
    protected function initializeDateRangeFilters(): void
    {
        $period = is_string($this->filters['period'] ?? null) ? $this->filters['period'] : '';

        if ($period === 'ytd') {
            $this->filters['period'] = 'year';
            $this->dateRangeFiltersUpdated();
        } elseif ($period === '') {
            $this->applyPresetRange($this->defaultDateRangePeriod());
        }

        if (! is_array($this->filters) || ! isset($this->filters['period'])) {
            $defaultPeriod = $this->defaultDateRangePeriod();
            $range = AnalyticsDateRange::fromFilters(['period' => $defaultPeriod]);

            $this->filters = array_merge([
                'period' => $defaultPeriod,
                'startDate' => $range->start->toDateString(),
                'endDate' => $range->end->toDateString(),
            ], is_array($this->filters) ? $this->filters : []);
        }
    }

    /** Initialize the range when the page is loaded without persisted filters. */
    public function ensureDefaultFilters(): void
    {
        if (! is_array($this->filters) || ! isset($this->filters['period']) || $this->filters['period'] === '') {
            $this->applyPresetRange($this->defaultDateRangePeriod());
        } elseif ($this->filters['period'] === 'ytd') {
            $this->filters['period'] = 'year';
            $this->dateRangeFiltersUpdated();
        }
    }

    /**
     * Apply a date-range preset or prepare the custom date controls.
     *
     * @param  'all'|'today'|'7days'|'30days'|'month'|'quarter'|'year'|'custom'  $period
     */
    public function setPeriod(string $period): void
    {
        if (($period === 'all') && $this->supportsAllDateRange()) {
            $this->filters = [
                'period' => 'all',
                'startDate' => null,
                'endDate' => null,
            ];

            $this->dateRangeFiltersUpdated();

            return;
        }

        if ($period === 'custom') {
            $today = CarbonImmutable::today(AppConfig::timezone());
            $startDate = is_string($this->filters['startDate'] ?? null) ? $this->filters['startDate'] : null;
            $endDate = is_string($this->filters['endDate'] ?? null) ? $this->filters['endDate'] : null;

            $this->filters = [
                ...($this->filters ?? []),
                'period' => 'custom',
                'startDate' => $startDate ?: $today->subDays(6)->toDateString(),
                'endDate' => $endDate ?: $today->toDateString(),
            ];

            $this->dateRangeFiltersUpdated();

            return;
        }

        $this->applyPresetRange($period);
    }

    /** Apply the dates entered in the custom range controls. */
    public function applyCustomRangeFromFilters(): void
    {
        $startDate = is_string($this->filters['startDate'] ?? null) ? $this->filters['startDate'] : '';
        $endDate = is_string($this->filters['endDate'] ?? null) ? $this->filters['endDate'] : '';

        if (($startDate === '') || ($endDate === '')) {
            return;
        }

        $this->applyCustomRange($startDate, $endDate);
    }

    /**
     * Apply a preset range.
     *
     * @param  'today'|'7days'|'30days'|'month'|'quarter'|'year'  $preset
     */
    private function applyPresetRange(string $preset): void
    {
        $today = CarbonImmutable::today(AppConfig::timezone());

        [$start, $end, $period] = match ($preset) {
            'today' => [$today, $today, 'today'],
            '30days' => [$today->subDays(29), $today, '30days'],
            'quarter' => [$today->startOfQuarter(), $today, 'quarter'],
            'year' => [$today->startOfYear(), $today, 'year'],
            default => [$today->subDays(6), $today, '7days'],
        };

        if ($preset === 'month') {
            $start = $today->startOfMonth();
            $end = $today;
            $period = 'month';
        }

        $this->filters = [
            'period' => $period,
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
        ];

        $this->dateRangeFiltersUpdated();
    }

    /** Apply and normalize a custom range. */
    private function applyCustomRange(string $startDate, string $endDate): void
    {
        $timezone = AppConfig::timezone();

        $start = CarbonImmutable::parse($startDate, $timezone)->startOfDay();
        $end = CarbonImmutable::parse($endDate, $timezone)->endOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        $this->filters = [
            'period' => 'custom',
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
        ];

        $this->dateRangeFiltersUpdated();
    }

    /** Whether this page may show records without a date constraint. */
    protected function supportsAllDateRange(): bool
    {
        return false;
    }

    /** The initial period used when the page has no saved filter state. */
    protected function defaultDateRangePeriod(): string
    {
        return '7days';
    }

    /** @return array<string, string> */
    private function dateRangePeriodOptions(): array
    {
        return [
            ...($this->supportsAllDateRange() ? ['all' => __('app.common.all')] : []),
            'today' => __('app.dashboard.filters.periods.today'),
            '7days' => __('app.dashboard.filters.periods.7days'),
            '30days' => __('app.dashboard.filters.periods.30days'),
            'month' => __('app.dashboard.filters.periods.month'),
            'quarter' => __('app.dashboard.filters.periods.quarter'),
            'year' => __('app.dashboard.filters.periods.year'),
            'custom' => __('app.dashboard.filters.periods.custom'),
        ];
    }

    /** Persist the range and refresh state derived from it. */
    private function dateRangeFiltersUpdated(): void
    {
        $this->updatedFilters();

        if (property_exists($this, 'cachedTabs')) {
            unset($this->cachedTabs);
        }

        if (method_exists($this, 'resetPage') && isset($this->table)) {
            $this->resetPage();
        }
    }
}
