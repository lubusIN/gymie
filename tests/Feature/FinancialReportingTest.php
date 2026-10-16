<?php

use App\Enums\ExpensePaymentMethod;
use App\Enums\Status;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Widgets\Analytics\ExpenseCategoriesDoughnutChartWidget;
use App\Filament\Widgets\InvoiceOverviewWidget;
use App\Helpers\Helpers;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-30 10:00:00');
    Helpers::setTestSettingsOverride([
        'general' => ['currency' => 'INR'],
        'charges' => ['taxes' => 0, 'discounts' => []],
        'expenses' => ['categories' => ['Rent', 'Utilities']],
        'subscriptions' => ['expiring_days' => 7],
        'payments' => ['provider' => 'stripe'],
        'notifications' => ['email' => []],
    ]);

    Role::findOrCreate('super_admin', 'web');
    $this->actingAs(User::factory()->create()->assignRole('super_admin'));
    Filament::setCurrentPanel('admin');
});

afterEach(function (): void {
    Carbon::setTestNow();
    Helpers::setTestSettingsOverride(null);
});

it('keeps secondary invoice amounts hidden and removes table summaries', function (): void {
    Livewire::test(ListInvoices::class)
        ->assertTableColumnVisible('total_amount')
        ->assertTableColumnVisible('due_amount')
        ->assertTableColumnExists(
            'subscription_fee',
            fn ($column): bool => $column->isToggleable()
                && $column->isToggledHiddenByDefault()
                && $column->isToggledHidden(),
        )
        ->assertTableColumnExists(
            'discount_amount',
            fn ($column): bool => $column->isToggleable()
                && $column->isToggledHiddenByDefault()
                && $column->isToggledHidden(),
        )
        ->assertTableColumnExists(
            'paid_amount',
            fn ($column): bool => $column->isToggleable()
                && $column->isToggledHiddenByDefault()
                && $column->isToggledHidden(),
        )
        ->assertTableColumnExists(
            'tax',
            fn ($column): bool => $column->isToggleable()
                && $column->isToggledHiddenByDefault()
                && $column->isToggledHidden(),
        )
        ->assertTableColumnExists('total_amount', fn ($column): bool => $column->getSummarizers() === [])
        ->assertTableColumnExists('due_amount', fn ($column): bool => $column->getSummarizers() === [])
        ->assertTableColumnExists(
            'id',
            fn ($column): bool => $column->isToggleable()
                && $column->isToggledHiddenByDefault()
                && $column->isToggledHidden(),
        );
});

it('summarizes invoices in the overview card for the selected period', function (): void {
    Invoice::factory()->create([
        'date' => '2026-09-05',
        'due_date' => '2026-09-15',
        'subscription_fee' => 100,
        'discount_amount' => 0,
        'paid_amount' => 100,
    ]);
    Invoice::factory()->create([
        'date' => '2026-09-10',
        'due_date' => '2026-10-05',
        'subscription_fee' => 200,
        'discount_amount' => 0,
        'paid_amount' => 50,
    ]);
    Invoice::factory()->create([
        'date' => '2026-09-30',
        'due_date' => '2026-09-20',
        'subscription_fee' => 300,
        'discount_amount' => 0,
        'paid_amount' => 0,
    ]);
    Invoice::factory()->create([
        'date' => '2026-09-20',
        'due_date' => '2026-09-25',
        'status' => Status::Cancelled,
        'subscription_fee' => 400,
        'discount_amount' => 0,
        'paid_amount' => 0,
    ]);
    Invoice::factory()->create([
        'date' => '2026-08-15',
        'due_date' => '2026-08-20',
        'subscription_fee' => 901,
        'discount_amount' => 0,
        'paid_amount' => 0,
    ]);

    Livewire::test(InvoiceOverviewWidget::class, [
        'pageFilters' => [
            'period' => 'custom',
            'startDate' => '2026-09-01',
            'endDate' => '2026-09-30',
        ],
    ])
        ->assertSee(__('app.summaries.total_invoiced'))
        ->assertSee(__('app.summaries.paid'))
        ->assertSee(__('app.summaries.outstanding'))
        ->assertSee(Status::Overdue->getLabel())
        ->assertSee(Helpers::formatCurrency(600))
        ->assertSee(Helpers::formatCurrency(150))
        ->assertSee(Helpers::formatCurrency(450))
        ->assertSee(Helpers::formatCurrency(300))
        ->assertSee('-33.4%')
        ->assertSee(__('app.widgets.vs_previous_period', [
            'count' => Helpers::formatCurrency(901),
        ]));
});

it('filters invoices by period, member, plan, and active status tab', function (): void {
    $member = Member::factory()->create(['name' => 'Alex Member']);
    $otherMember = Member::factory()->create(['name' => 'Other Member']);
    $plan = Plan::factory()->create(['name' => 'Monthly']);
    $otherPlan = Plan::factory()->create(['name' => 'Quarterly']);

    $matchingSubscription = Subscription::factory()->for($member)->for($plan)->create();
    $partialSubscription = Subscription::factory()->for($member)->for($plan)->create();
    $otherMemberSubscription = Subscription::factory()->for($otherMember)->for($plan)->create();
    $otherPlanSubscription = Subscription::factory()->for($member)->for($otherPlan)->create();

    $matching = Invoice::factory()->for($matchingSubscription)->create([
        'number' => 'INV-MATCH',
        'date' => '2026-09-10',
        'due_date' => '2026-09-20',
        'subscription_fee' => 100,
        'discount_amount' => 10,
        'paid_amount' => 90,
    ])->refresh();
    $otherMemberInvoice = Invoice::factory()->for($otherMemberSubscription)->create([
        'number' => 'INV-OTHER-MEMBER',
        'date' => '2026-09-11',
        'due_date' => '2026-10-11',
        'subscription_fee' => 200,
        'discount_amount' => 0,
        'paid_amount' => 200,
    ])->refresh();
    $otherPlanInvoice = Invoice::factory()->for($otherPlanSubscription)->create([
        'number' => 'INV-OTHER-PLAN',
        'date' => '2026-09-12',
        'due_date' => '2026-10-12',
        'subscription_fee' => 300,
        'discount_amount' => 0,
        'paid_amount' => 300,
    ])->refresh();
    $partial = Invoice::factory()->for($partialSubscription)->create([
        'number' => 'INV-PARTIAL',
        'date' => '2026-09-13',
        'due_date' => '2026-10-13',
        'subscription_fee' => 400,
        'discount_amount' => 0,
        'paid_amount' => 100,
    ])->refresh();

    Livewire::test(ListInvoices::class)
        ->call('setPeriod', 'custom')
        ->set('filters.startDate', '2026-09-01')
        ->set('filters.endDate', '2026-09-30')
        ->call('applyCustomRangeFromFilters')
        ->set('activeTab', Status::Paid->value)
        ->filterTable('member', $member->getKey())
        ->filterTable('plan', $plan->getKey())
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$otherMemberInvoice, $otherPlanInvoice, $partial]);
});

it('shows invoices from every date when the date filter is cleared', function (): void {
    $recentInvoice = Invoice::factory()->create([
        'date' => '2026-09-30',
        'due_date' => '2026-10-29',
        'subscription_fee' => 100,
        'discount_amount' => 0,
        'paid_amount' => 100,
    ]);
    $olderInvoice = Invoice::factory()->create([
        'date' => '2025-01-15',
        'due_date' => '2025-02-15',
        'subscription_fee' => 100,
        'discount_amount' => 0,
        'paid_amount' => 100,
    ]);

    $component = Livewire::test(ListInvoices::class)
        ->assertCanSeeTableRecords([$recentInvoice])
        ->assertCanNotSeeTableRecords([$olderInvoice]);

    expect($component->instance()->getCachedTabs()[Status::Paid->value]->getBadge())->toBe('1');

    $component
        ->call('setPeriod', 'today')
        ->assertSet('filters.period', 'today')
        ->assertSet('filters.startDate', '2026-09-30')
        ->assertSet('filters.endDate', '2026-09-30')
        ->assertCanSeeTableRecords([$recentInvoice])
        ->assertCanNotSeeTableRecords([$olderInvoice])
        ->fillForm(['period' => null], 'dateRangeForm')
        ->assertSet('filters.period', 'all')
        ->assertCanSeeTableRecords([$recentInvoice, $olderInvoice]);

    expect($component->instance()->getCachedTabs()[Status::Paid->value]->getBadge())->toBe('2');

    Livewire::test(InvoiceOverviewWidget::class, [
        'pageFilters' => ['period' => 'all'],
    ])->assertSee(Helpers::formatCurrency(200));
});

it('shows member context and overdue age in the invoice list', function (): void {
    $member = Member::factory()->create([
        'name' => 'Taylor Member',
        'code' => 'MEM-100',
    ]);
    $plan = Plan::factory()->create(['name' => 'Strength 30']);
    $subscription = Subscription::factory()->for($member)->for($plan)->create();
    $invoice = Invoice::factory()->for($subscription)->create([
        'number' => 'INV-OVERDUE',
        'date' => '2026-09-20',
        'due_date' => '2026-09-25',
        'subscription_fee' => 500,
        'discount_amount' => 0,
        'paid_amount' => 0,
    ]);

    Livewire::test(ListInvoices::class)
        ->call('setPeriod', 'custom')
        ->set('filters.startDate', '2026-09-01')
        ->set('filters.endDate', '2026-09-30')
        ->call('applyCustomRangeFromFilters')
        ->assertCanSeeTableRecords([$invoice])
        ->assertSee('Taylor Member')
        ->assertSee('MEM-100')
        ->assertDontSee('MEM-100 · Strength 30')
        ->assertSee('5 days overdue');
});

it('orders expense categories from the highest amount to the lowest', function (): void {
    Expense::factory()
        ->count(7)
        ->sequence(
            ['category' => 'Largest', 'amount' => 700],
            ['category' => 'Second', 'amount' => 600],
            ['category' => 'Third', 'amount' => 500],
            ['category' => 'Fourth', 'amount' => 400],
            ['category' => 'Fifth', 'amount' => 300],
            ['category' => 'Sixth', 'amount' => 290],
            ['category' => 'Seventh', 'amount' => 280],
        )
        ->create([
            'date' => '2026-09-15',
            'paid_at' => '2026-09-15 10:00:00',
            'status' => Status::Paid,
        ]);

    Livewire::test(ExpenseCategoriesDoughnutChartWidget::class, [
        'pageFilters' => [
            'period' => 'month',
        ],
    ])
        ->assertSeeInOrder([
            'Largest',
            'Second',
            __('app.analytics.other'),
            'Third',
            'Fourth',
            'Fifth',
        ]);
});

it('exports only invoices represented by the active table state', function (): void {
    $member = Member::factory()->create(['name' => 'Alex Member']);
    $otherMember = Member::factory()->create(['name' => 'Other Member']);
    $plan = Plan::factory()->create(['name' => 'Monthly']);
    $matchingSubscription = Subscription::factory()->for($member)->for($plan)->create();
    $otherSubscription = Subscription::factory()->for($otherMember)->for($plan)->create();

    Invoice::factory()->for($matchingSubscription)->create([
        'number' => 'INV-MATCH',
        'date' => '2026-09-10',
        'due_date' => '2026-09-20',
        'subscription_fee' => 100,
        'discount_amount' => 10,
        'paid_amount' => 90,
    ]);
    Invoice::factory()->for($otherSubscription)->create([
        'number' => 'INV-EXCLUDED',
        'date' => '2026-09-11',
        'due_date' => '2026-10-11',
        'subscription_fee' => 200,
        'discount_amount' => 0,
        'paid_amount' => 200,
    ]);

    $expectedCsv = csvContent([
        [
            __('app.fields.invoice_number'),
            __('app.fields.member'),
            __('app.fields.plan'),
            __('app.fields.invoice_date'),
            __('app.fields.due_date'),
            __('app.fields.status'),
            __('app.invoices.pdf.subtotal'),
            __('app.fields.discount'),
            __('app.fields.tax'),
            __('app.fields.total'),
            __('app.fields.paid'),
            __('app.fields.due'),
        ],
        [
            'INV-MATCH',
            'Alex Member',
            'Monthly',
            '2026-09-10',
            '2026-09-20',
            Status::Paid->getLabel(),
            100.0,
            10.0,
            0.0,
            90.0,
            90.0,
            0.0,
        ],
    ]);

    Livewire::test(ListInvoices::class)
        ->call('setPeriod', 'custom')
        ->set('filters.startDate', '2026-09-01')
        ->set('filters.endDate', '2026-09-30')
        ->call('applyCustomRangeFromFilters')
        ->set('activeTab', Status::Paid->value)
        ->filterTable('member', $member->getKey())
        ->filterTable('plan', $plan->getKey())
        ->callAction('export_csv')
        ->assertFileDownloaded(
            'invoices-2026-09-30.csv',
            $expectedCsv,
            'text/csv; charset=UTF-8',
        );
});

it('filters, searches, and safely exports expenses', function (): void {
    $matching = Expense::factory()->create([
        'name' => '=SUM(1,1)',
        'amount' => 500,
        'date' => '2026-09-12',
        'due_date' => '2026-09-15',
        'paid_at' => '2026-09-14 13:45:00',
        'category' => 'rent',
        'vendor' => 'Property Owner',
        'status' => Status::Paid,
        'payment_method' => ExpensePaymentMethod::Cash,
        'payment_reference' => 'CASH-10',
    ]);
    $otherPaymentMethod = Expense::factory()->create([
        'name' => 'Card rent',
        'amount' => 700,
        'status' => Status::Paid,
        'payment_method' => ExpensePaymentMethod::Card,
    ]);
    $otherStatus = Expense::factory()->create([
        'name' => 'Pending rent',
        'amount' => 900,
        'status' => Status::Pending,
        'payment_method' => ExpensePaymentMethod::Cash,
    ]);

    $expectedCsv = csvContent([
        [
            __('app.fields.expense_name'),
            __('app.fields.category'),
            __('app.fields.vendor'),
            __('app.fields.date'),
            __('app.fields.due_date'),
            __('app.fields.paid_at'),
            __('app.fields.status'),
            __('app.fields.amount'),
            __('app.fields.payment_method'),
            __('app.fields.payment_reference'),
        ],
        [
            "'=SUM(1,1)",
            __('app.expense_categories.rent'),
            'Property Owner',
            '2026-09-12',
            '2026-09-15',
            '2026-09-14 13:45:00',
            Status::Paid->getLabel(),
            500.0,
            ExpensePaymentMethod::Cash->getLabel(),
            'CASH-10',
        ],
    ]);

    Livewire::test(ListExpenses::class)
        ->call('setPeriod', 'custom')
        ->set('filters.startDate', '2026-09-01')
        ->set('filters.endDate', '2026-09-30')
        ->call('applyCustomRangeFromFilters')
        ->set('activeTab', Status::Paid->value)
        ->filterTable('payment_method', ExpensePaymentMethod::Cash->value)
        ->searchTable('SUM')
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$otherPaymentMethod, $otherStatus])
        ->callAction('export_csv')
        ->assertFileDownloaded(
            'expenses-2026-09-30.csv',
            $expectedCsv,
            'text/csv; charset=UTF-8',
        );
});

/**
 * Build the byte-for-byte CSV representation expected from an export.
 *
 * @param  list<list<float|int|string|null>>  $rows
 */
function csvContent(array $rows): string
{
    $stream = fopen('php://temp', 'w+b');

    if ($stream === false) {
        throw new RuntimeException('Unable to open the test CSV stream.');
    }

    fwrite($stream, "\xEF\xBB\xBF");

    foreach ($rows as $row) {
        fputcsv($stream, $row);
    }

    rewind($stream);
    $content = stream_get_contents($stream);
    fclose($stream);

    if ($content === false) {
        throw new RuntimeException('Unable to read the test CSV stream.');
    }

    return $content;
}
