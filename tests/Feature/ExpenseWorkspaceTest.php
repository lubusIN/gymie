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

it('summarizes paid, pending, and overdue expenses without counting cancelled records', function (): void {
    Expense::factory()->create([
        'amount' => 100,
        'status' => Status::Paid,
        'paid_at' => '2026-09-10 10:00:00',
    ]);
    Expense::factory()->create([
        'amount' => 200,
        'status' => Status::Pending,
        'paid_at' => null,
    ]);
    Expense::factory()->create([
        'amount' => 300,
        'status' => Status::Overdue,
        'paid_at' => null,
    ]);
    Expense::factory()->create([
        'amount' => 400,
        'status' => Status::Cancelled,
        'paid_at' => null,
    ]);

    Livewire::test(ExpenseOverviewWidget::class)
        ->assertSee('Paid expenses this month')
        ->assertSee('Pending expenses')
        ->assertSee('Overdue expenses')
        ->assertSee(Helpers::formatCurrency(100))
        ->assertSee(Helpers::formatCurrency(200))
        ->assertSee(Helpers::formatCurrency(300))
        ->assertDontSee(Helpers::formatCurrency(400));
});

it('filters expenses by date, category, and vendor', function (): void {
    $matching = Expense::factory()->create([
        'name' => 'September rent',
        'date' => '2026-09-05',
        'category' => 'rent',
        'vendor' => 'Property Owner',
    ]);
    $other = Expense::factory()->create([
        'name' => 'August utilities',
        'date' => '2026-08-05',
        'category' => 'utilities',
        'vendor' => 'Power Company',
    ]);

    Livewire::test(ListExpenses::class)
        ->filterTable('date', [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
        ])
        ->filterTable('category', 'rent')
        ->filterTable('vendor', 'Property Owner')
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$other]);
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
