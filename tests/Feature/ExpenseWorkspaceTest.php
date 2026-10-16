<?php

use App\Enums\Status;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Widgets\ExpenseOverviewWidget;
use App\Helpers\Helpers;
use App\Models\Expense;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-28 10:00:00');
    Helpers::setTestSettingsOverride([
        'general' => ['currency' => 'INR'],
        'expenses' => ['categories' => ['Rent', 'Utilities']],
    ]);

    Role::findOrCreate('super_admin', 'web');
    $this->actingAs(User::factory()->create()->assignRole('super_admin'));
    Filament::setCurrentPanel('admin');
});

afterEach(function (): void {
    Carbon::setTestNow();
    Helpers::setTestSettingsOverride(null);
});

it('summarizes expenses by status for the selected date range', function (): void {
    Expense::factory()->create([
        'amount' => 100,
        'date' => '2026-09-10',
        'status' => Status::Paid,
        'paid_at' => '2026-09-10 10:00:00',
    ]);
    Expense::factory()->create([
        'amount' => 200,
        'date' => '2026-09-15',
        'status' => Status::Pending,
        'paid_at' => null,
    ]);
    Expense::factory()->create([
        'amount' => 300,
        'date' => '2026-09-20',
        'status' => Status::Overdue,
        'paid_at' => null,
    ]);
    Expense::factory()->create([
        'amount' => 400,
        'date' => '2026-09-22',
        'status' => Status::Cancelled,
        'paid_at' => null,
    ]);
    Expense::factory()->create([
        'amount' => 901,
        'date' => '2026-08-31',
        'status' => Status::Paid,
        'paid_at' => '2026-09-10 10:00:00',
    ]);

    Livewire::test(ExpenseOverviewWidget::class, [
        'pageFilters' => [
            'period' => 'custom',
            'startDate' => '2026-09-01',
            'endDate' => '2026-09-30',
        ],
    ])
        ->assertSee('Total expenses')
        ->assertSee('Paid expenses')
        ->assertSee('Pending expenses')
        ->assertSee('Overdue expenses')
        ->assertSee(Helpers::formatCurrency(600))
        ->assertSee(Helpers::formatCurrency(100))
        ->assertSee(Helpers::formatCurrency(200))
        ->assertSee(Helpers::formatCurrency(300))
        ->assertSee('-33.4%')
        ->assertDontSee(Helpers::formatCurrency(400))
        ->assertSee(__('app.widgets.vs_previous_period', [
            'count' => Helpers::formatCurrency(901),
        ]));

    Livewire::test(ExpenseOverviewWidget::class, [
        'pageFilters' => ['period' => 'all'],
    ])->assertSee(Helpers::formatCurrency(1501));
});

it('changes the expense workspace date range from the header filter', function (): void {
    Livewire::test(ListExpenses::class)
        ->assertSet('filters.period', '7days')
        ->assertSet('filters.startDate', '2026-09-22')
        ->assertSet('filters.endDate', '2026-09-28')
        ->assertSchemaComponentHidden('startDate', 'dateRangeForm')
        ->assertSchemaComponentHidden('endDate', 'dateRangeForm')
        ->call('setPeriod', 'today')
        ->assertSet('filters.period', 'today')
        ->assertSet('filters.startDate', '2026-09-28')
        ->assertSet('filters.endDate', '2026-09-28')
        ->call('setPeriod', 'custom')
        ->assertSchemaComponentVisible('startDate', 'dateRangeForm')
        ->assertSchemaComponentVisible('endDate', 'dateRangeForm')
        ->call('setPeriod', '30days')
        ->assertSet('filters.period', '30days')
        ->assertSet('filters.startDate', '2026-08-30')
        ->assertSet('filters.endDate', '2026-09-28')
        ->assertSchemaComponentHidden('startDate', 'dateRangeForm')
        ->assertSchemaComponentHidden('endDate', 'dateRangeForm')
        ->fillForm(['period' => null], 'dateRangeForm')
        ->assertSet('filters.period', 'all')
        ->assertSet('filters.startDate', null)
        ->assertSet('filters.endDate', null);
});

it('scopes expense status tab counts to the selected date range', function (): void {
    Expense::factory()->create([
        'date' => '2026-09-25',
        'status' => Status::Pending,
    ]);
    Expense::factory()->create([
        'date' => '2026-08-25',
        'status' => Status::Pending,
    ]);
    Expense::factory()->create([
        'date' => '2026-09-26',
        'status' => Status::Paid,
    ]);
    Expense::factory()->create([
        'date' => '2026-08-26',
        'status' => Status::Overdue,
    ]);

    $tabs = Livewire::test(ListExpenses::class)->instance()->getCachedTabs();

    expect($tabs[Status::Pending->value]->getBadge())->toBe('1')
        ->and($tabs[Status::Paid->value]->getBadge())->toBe('1')
        ->and($tabs[Status::Overdue->value]->getBadge())->toBe('0')
        ->and($tabs[Status::Cancelled->value]->getBadge())->toBe('0');
});

it('uses the workspace date range and table filters for rows and exports', function (): void {
    $matching = Expense::factory()->create([
        'name' => 'September rent',
        'date' => '2026-09-05',
        'category' => 'rent',
        'vendor' => 'Property Owner',
    ]);
    $outsideRange = Expense::factory()->create([
        'name' => 'August utilities',
        'date' => '2026-08-05',
        'category' => 'rent',
        'vendor' => 'Property Owner',
    ]);
    $otherCategory = Expense::factory()->create([
        'name' => 'September utilities',
        'date' => '2026-09-06',
        'category' => 'utilities',
        'vendor' => 'Power Company',
    ]);
    $otherVendor = Expense::factory()->create([
        'name' => 'September maintenance',
        'date' => '2026-09-07',
        'category' => 'rent',
        'vendor' => 'Maintenance Company',
    ]);

    $component = Livewire::test(ListExpenses::class)
        ->call('setPeriod', 'custom')
        ->set('filters.startDate', '2026-09-01')
        ->set('filters.endDate', '2026-09-30')
        ->call('applyCustomRangeFromFilters')
        ->filterTable('category', 'rent')
        ->filterTable('vendor', 'Property Owner')
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$outsideRange, $otherCategory, $otherVendor]);

    expect($component->instance()->getTableQueryForExport()->pluck('id')->all())
        ->toBe([$matching->getKey()]);
});

it('shows onboarding guidance when no expenses have been created', function (): void {
    Livewire::test(ListExpenses::class)
        ->assertSee(__('app.empty.no_records', ['records' => __('app.resources.expenses.plural')]))
        ->assertSee(__('app.empty.create_to_get_started', [
            'resource' => __('app.resources.expenses.singular'),
        ]));
});

it('shows the selected range when expenses exist outside it', function (): void {
    Expense::factory()->create([
        'date' => '2026-09-01',
    ]);

    Livewire::test(ListExpenses::class)
        ->assertSee(__('app.empty.no_records_in_range', [
            'records' => __('app.resources.expenses.plural'),
        ]))
        ->assertSee(__('app.empty.found_none_between', [
            'records' => __('app.resources.expenses.plural'),
            'from' => '22-09-2026',
            'to' => '28-09-2026',
        ]));
});

it('marks only past-due pending expenses as overdue', function (): void {
    $pastDue = Expense::factory()->create([
        'status' => Status::Pending,
        'due_date' => '2026-09-27',
        'paid_at' => null,
    ]);
    $dueToday = Expense::factory()->create([
        'status' => Status::Pending,
        'due_date' => '2026-09-28',
        'paid_at' => null,
    ]);
    $paid = Expense::factory()->create([
        'status' => Status::Paid,
        'due_date' => '2026-09-27',
        'paid_at' => '2026-09-26 10:00:00',
    ]);

    $this->artisan('gymie:expenses')
        ->expectsOutput('1 expense(s) marked as overdue.')
        ->assertSuccessful();

    expect($pastDue->refresh()->status)->toBe(Status::Overdue)
        ->and($dueToday->refresh()->status)->toBe(Status::Pending)
        ->and($paid->refresh()->status)->toBe(Status::Paid);
});

it('requires and returns payment details when the API records a paid expense', function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $permission = Permission::findOrCreate('Create:Expense', 'web');
    $user = User::factory()->create();
    $user->givePermissionTo($permission);
    Sanctum::actingAs($user);

    $payload = [
        'name' => 'Equipment repair',
        'amount' => 1500,
        'date' => '2026-09-28',
        'category' => 'maintenance',
        'status' => 'paid',
    ];

    $this->postJson('/api/v1/expenses', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['paid_at', 'payment_method']);

    $this->postJson('/api/v1/expenses', [
        ...$payload,
        'paid_at' => '2026-09-28 09:30:00',
        'payment_method' => 'bank_transfer',
        'payment_reference' => 'BANK-123',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.payment_method', 'bank_transfer')
        ->assertJsonPath('data.payment_reference', 'BANK-123');
});
