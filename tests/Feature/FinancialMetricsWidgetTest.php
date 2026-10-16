<?php

use App\Filament\Widgets\Analytics\FinancialMetricsWidget;
use App\Helpers\Helpers;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
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

it('shows a loss and its decline when paid expenses exceed collections', function (): void {
    config()->set('app.timezone', 'UTC');
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-02-23', 'UTC'));

    Helpers::setTestSettingsOverride([
        'general' => ['currency' => 'INR'],
        'charges' => ['taxes' => 0, 'discounts' => []],
        'expenses' => ['categories' => ['Rent']],
        'subscriptions' => ['expiring_days' => 7],
        'payments' => ['provider' => 'stripe'],
    ]);

    $invoice = Invoice::factory()->create([
        'date' => '2026-02-18',
        'paid_amount' => 0,
    ]);

    InvoiceTransaction::query()->create([
        'invoice_id' => $invoice->id,
        'type' => 'payment',
        'amount' => 100,
        'occurred_at' => '2026-02-12 10:00:00',
        'payment_method' => 'offline',
        'reference_id' => 'test:previous-payment',
    ]);
    Expense::factory()->create([
        'amount' => 50,
        'date' => '2026-02-12',
        'paid_at' => '2026-02-12 10:00:00',
        'status' => 'paid',
    ]);

    InvoiceTransaction::query()->create([
        'invoice_id' => $invoice->id,
        'type' => 'payment',
        'amount' => 100,
        'occurred_at' => '2026-02-20 10:00:00',
        'payment_method' => 'offline',
        'reference_id' => 'test:current-payment',
    ]);
    Expense::factory()->create([
        'amount' => 150,
        'date' => '2026-02-20',
        'paid_at' => '2026-02-20 10:00:00',
        'status' => 'paid',
    ]);

    Livewire::test(FinancialMetricsWidget::class)
        ->assertSee(__('app.widgets.profit'))
        ->assertSee(Helpers::formatCurrency(-50))
        ->assertSee('-200%');
});
