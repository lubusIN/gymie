<?php

use App\Enums\ExpensePaymentMethod;
use App\Enums\Status;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
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

it('shows invoice discounts while keeping IDs hidden by default', function (): void {
    Livewire::test(ListInvoices::class)
        ->assertTableColumnVisible('discount_amount')
        ->assertTableColumnExists(
            'id',
            fn ($column): bool => $column->isToggleable()
                && $column->isToggledHiddenByDefault()
                && $column->isToggledHidden(),
        );
});

it('filters and summarizes invoices by member, plan, and active status tab', function (): void {
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
        'subscription_fee' => 200,
        'discount_amount' => 0,
        'paid_amount' => 200,
    ])->refresh();
    $otherPlanInvoice = Invoice::factory()->for($otherPlanSubscription)->create([
        'number' => 'INV-OTHER-PLAN',
        'subscription_fee' => 300,
        'discount_amount' => 0,
        'paid_amount' => 300,
    ])->refresh();
    $partial = Invoice::factory()->for($partialSubscription)->create([
        'number' => 'INV-PARTIAL',
        'subscription_fee' => 400,
        'discount_amount' => 0,
        'paid_amount' => 100,
    ])->refresh();

    Livewire::test(ListInvoices::class)
        ->set('activeTab', Status::Paid->value)
        ->filterTable('member', $member->getKey())
        ->filterTable('plan', $plan->getKey())
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$otherMemberInvoice, $otherPlanInvoice, $partial])
        ->assertTableColumnSummarySet('total_amount', 'total_invoiced', 90)
        ->assertTableColumnSummarySet('paid_amount', 'paid', 90)
        ->assertTableColumnSummarySet('due_amount', 'outstanding', 0);
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

it('filters, searches, summarizes, and safely exports expenses', function (): void {
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
        ->set('activeTab', Status::Paid->value)
        ->filterTable('payment_method', ExpensePaymentMethod::Cash->value)
        ->searchTable('SUM')
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$otherPaymentMethod, $otherStatus])
        ->assertTableColumnSummarySet('amount', 'total_expense', 500)
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
