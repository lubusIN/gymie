<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Analytics\CashflowTrendChartWidget;
use App\Filament\Widgets\Analytics\ExpenseCategoriesDoughnutChartWidget;
use App\Filament\Widgets\Analytics\FailedCommunicationDeliveriesTableWidget;
use App\Filament\Widgets\Analytics\FinancialMetricsWidget;
use App\Filament\Widgets\Analytics\MembershipMetricsWidget;
use App\Filament\Widgets\Analytics\MembershipOverviewSubscriptionsTableWidget;
use App\Filament\Widgets\Analytics\RecentTransactionsTableWidget;
use App\Support\AppConfig;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFilters;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;

/**
 * Main application dashboard.
 *
 * This dashboard hosts business analytics widgets (collected-based) and provides
 * a time-range filter that widgets can read via `InteractsWithPageFilters`.
 */
class Dashboard extends \Filament\Pages\Dashboard
{
    use HasFilters;

    protected static string $routePath = 'dashboard';

    protected static ?string $title = null;

    /**
     * Get the dashboard page title.
     */
    public function getTitle(): string
    {
        return __('app.dashboard.title');
    }

    /**
     * Get the dashboard navigation label.
     */
    public static function getNavigationLabel(): string
    {
        return __('app.navigation.dashboard');
    }

    /**
     * Render a custom header that includes a real select field for the date range.
     *
     * For a select-style control in the top-right, we render a custom header view
     * that binds directly to this Livewire component.
     */
    public function getHeader(): ?View
    {
        return view('filament.pages.dashboard-header');
    }

    /**
     * Dashboard header form schema (date range controls).
     *
     * We use Filament form components (non-native select) instead of raw HTML so
     * the control feels consistent with the rest of the admin UI.
     */
    public function form(Schema $schema): Schema
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
                    ->options([
                        '7days' => __('app.dashboard.filters.periods.7days'),
                        '30days' => __('app.dashboard.filters.periods.30days'),
                        'month' => __('app.dashboard.filters.periods.month'),
                        'quarter' => __('app.dashboard.filters.periods.quarter'),
                        'year' => __('app.dashboard.filters.periods.year'),
                        'custom' => __('app.dashboard.filters.periods.custom'),
                    ])
                    ->default('7days')
                    ->live()
                    ->afterStateUpdated(function (mixed $state): void {
                        if (in_array($state, ['7days', '30days', 'month', 'quarter', 'year', 'custom'], true)) {
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
                    ->visible(fn (Get $get): bool => $get('period') === 'custom')
                    ->live()
                    ->grow(false)
                    ->extraFieldWrapperAttributes([
                        'class' => 'w-full sm:w-40',
                    ]),
                DatePicker::make('endDate')
                    ->hiddenLabel()
                    ->placeholder(__('app.dashboard.filters.end'))
                    ->visible(fn (Get $get): bool => $get('period') === 'custom')
                    ->live()
                    ->grow(false)
                    ->extraFieldWrapperAttributes([
                        'class' => 'w-full sm:w-40',
                    ]),
            ]);
    }

    /**
     * Get the responsive dashboard column layout.
     */
    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 4,
        ];
    }

    /**
     * Render dashboard widgets in grouped layout blocks.
     *
     * We group Financial + Spending Overview together so the two cards always
     * sit side-by-side on larger screens.
     */
    public function getWidgetsContentComponent(): Component
    {
        $columns = $this->getColumns();

        return Grid::make(1)->schema([
            Grid::make($columns)->schema([
                ...$this->getWidgetsSchemaComponents([
                    MembershipMetricsWidget::class,
                ]),
            ]),
            Grid::make($columns)->schema(
                $this->getWidgetsSchemaComponents([
                    FinancialMetricsWidget::class,
                    ExpenseCategoriesDoughnutChartWidget::class,
                ]),
            ),
            Grid::make($columns)->schema([
                ...$this->getWidgetsSchemaComponents([
                    MembershipOverviewSubscriptionsTableWidget::class,
                    RecentTransactionsTableWidget::class,
                ]),
            ]),
            Grid::make($columns)->schema([
                ...$this->getWidgetsSchemaComponents([
                    FailedCommunicationDeliveriesTableWidget::class,
                ]),
            ]),
            Grid::make($columns)->schema(
                $this->getWidgetsSchemaComponents([
                    CashflowTrendChartWidget::class,
                ]),
            ),
        ]);
    }

    /**
     * Initialize dashboard state and filters on component mount.
     */
    public function mount(): void
    {
        if (method_exists(parent::class, 'mount')) {
            parent::mount();
        }

        $period = is_string($this->filters['period'] ?? null) ? $this->filters['period'] : '';

        if ($period === 'ytd') {
            $this->filters['period'] = 'year';
            $this->updatedFilters();
        } elseif ($period === '') {
            $this->applyPresetRange('7days');
        }

        if (! is_array($this->filters) || ! isset($this->filters['period'])) {
            $today = CarbonImmutable::today(AppConfig::timezone());
            $this->filters = array_merge([
                'period' => '7days',
                'startDate' => $today->subDays(6)->toDateString(),
                'endDate' => $today->toDateString(),
            ], is_array($this->filters) ? $this->filters : []);
        }
    }

    /**
     * Initialize the filters for visits where there is no persisted state.
     */
    public function ensureDefaultFilters(): void
    {
        if (! is_array($this->filters) || ! isset($this->filters['period']) || $this->filters['period'] === '') {
            $this->applyPresetRange('7days');
        } elseif ($this->filters['period'] === 'ytd') {
            $this->filters['period'] = 'year';
            $this->updatedFilters();
        }
    }

    /**
     * Handle selecting a period from the dashboard header select.
     *
     * @param  '7days'|'30days'|'month'|'quarter'|'year'|'custom'  $period
     */
    public function setPeriod(string $period): void
    {
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

            $this->updatedFilters();

            return;
        }

        $this->applyPresetRange($period);
    }

    /**
     * Apply the currently selected custom range (start & end dates).
     */
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
     * Apply a preset range to the dashboard filters.
     *
     * @param  '7days'|'30days'|'month'|'quarter'|'year'  $preset
     */
    private function applyPresetRange(string $preset): void
    {
        $today = CarbonImmutable::today(AppConfig::timezone());

        [$start, $end, $period] = match ($preset) {
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

        $this->updatedFilters();
    }

    /**
     * Apply a custom range to the dashboard filters.
     */
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

        $this->updatedFilters();
    }
}
