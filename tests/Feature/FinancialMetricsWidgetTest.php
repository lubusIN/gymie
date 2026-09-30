<?php

use App\Filament\Widgets\Analytics\FinancialMetricsWidget;
use App\Helpers\Helpers;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Helpers::setTestSettingsOverride(null);
});

it('renders the financial metrics in one overview card', function (): void {
    config()->set('app.timezone', 'UTC');
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-02-23', 'UTC'));

    Helpers::setTestSettingsOverride([
        'general' => [
            'currency' => 'INR',
        ],
        'charges' => [
            'taxes' => 0,
            'discounts' => [],
        ],
        'subscriptions' => [
            'expiring_days' => 7,
        ],
        'payments' => [
            'provider' => 'stripe',
        ],
    ]);

    Livewire::test(FinancialMetricsWidget::class)
        ->assertSee(__('app.widgets.financial_overview'))
        ->assertSee(__('app.widgets.net_revenue'))
        ->assertSee(__('app.widgets.total_collected'))
        ->assertSee(__('app.widgets.outstanding_payments'))
        ->assertSee(__('app.widgets.profit'))
        ->assertSeeHtml('data-financial-metrics')
        ->assertSeeHtml('data-financial-trend')
        ->assertSee('0%');
});
