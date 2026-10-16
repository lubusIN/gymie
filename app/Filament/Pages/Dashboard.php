<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Analytics\CashflowTrendChartWidget;
use App\Filament\Widgets\Analytics\ExpenseCategoriesDoughnutChartWidget;
use App\Filament\Widgets\Analytics\FailedCommunicationDeliveriesTableWidget;
use App\Filament\Widgets\Analytics\FinancialMetricsWidget;
use App\Filament\Widgets\Analytics\MembershipMetricsWidget;
use App\Filament\Widgets\Analytics\MembershipOverviewSubscriptionsTableWidget;
use App\Filament\Widgets\Analytics\RecentTransactionsTableWidget;
use App\Support\Filament\HasDateRangeFilters;
use Filament\Pages\Dashboard\Concerns\HasFilters;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
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
    use HasDateRangeFilters;
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
        return $this->configureDateRangeFilters($schema);
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

        $this->initializeDateRangeFilters();
    }
}
