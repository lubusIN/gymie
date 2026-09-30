<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\Analytics\ExpenseCategoriesDoughnutChartWidget;
use App\Filament\Widgets\Analytics\FinancialMetricsWidget;
use App\Filament\Widgets\Analytics\MembershipMetricsWidget;
use App\Filament\Widgets\Analytics\MembershipOverviewSubscriptionsTableWidget;
use App\Filament\Widgets\Analytics\RecentTransactionsTableWidget;
use App\Filament\Widgets\AttendanceOverviewWidget;
use Filament\Schemas\Components\Livewire;

/**
 * Collect Livewire component class names from a schema component tree.
 *
 * @return array<int, string>
 */
function collectLivewireComponentsFromSchema(mixed $component): array
{
    $components = [];

    if ($component instanceof Livewire) {
        $components[] = $component->getComponent();
    }

    if (! is_object($component)) {
        return $components;
    }

    $reflection = new ReflectionObject($component);

    if (! $reflection->hasProperty('childComponents')) {
        return $components;
    }

    $property = $reflection->getProperty('childComponents');
    $property->setAccessible(true);

    /** @var mixed $childComponents */
    $childComponents = $property->getValue($component);

    if (! is_array($childComponents)) {
        return $components;
    }

    foreach ($childComponents as $breakpointSchema) {
        if (! is_array($breakpointSchema)) {
            continue;
        }

        foreach ($breakpointSchema as $childComponent) {
            $components = [
                ...$components,
                ...collectLivewireComponentsFromSchema($childComponent),
            ];
        }
    }

    return $components;
}

it('renders the dashboard grid as 4 columns on md', function (): void {
    $dashboard = new Dashboard;

    expect($dashboard->getColumns())->toBe([
        'default' => 1,
        'md' => 4,
    ]);
});

it('includes membership overview on the dashboard', function (): void {
    $dashboard = new Dashboard;

    $widgetsComponent = $dashboard->getWidgetsContentComponent();

    $components = collectLivewireComponentsFromSchema($widgetsComponent);

    expect($components)->toContain(MembershipMetricsWidget::class);
    expect($components)->toContain(MembershipOverviewSubscriptionsTableWidget::class);
});

it('keeps attendance overview out of the main dashboard', function (): void {
    $dashboard = new Dashboard;

    $widgetsComponent = $dashboard->getWidgetsContentComponent();

    $components = collectLivewireComponentsFromSchema($widgetsComponent);

    expect($components)->not->toContain(AttendanceOverviewWidget::class);
});

it('positions spending overview next to financial at md', function (): void {
    $financial = new FinancialMetricsWidget;
    $spending = new ExpenseCategoriesDoughnutChartWidget;

    expect($financial->getColumnSpan())->toBe([
        'default' => 1,
        'md' => 2,
    ]);

    expect($spending->getColumnSpan())->toBe([
        'default' => 1,
        'md' => 2,
    ]);

    expect(ExpenseCategoriesDoughnutChartWidget::getSort())->toBeGreaterThan(FinancialMetricsWidget::getSort());
});

it('keeps membership and recent transactions full width on md', function (): void {
    $membership = new MembershipMetricsWidget;
    $recentTransactions = new RecentTransactionsTableWidget;

    expect($membership->getColumnSpan())->toBe('full');

    expect($recentTransactions->getColumnSpan())->toBe([
        'default' => 1,
        'md' => 2,
    ]);
});
