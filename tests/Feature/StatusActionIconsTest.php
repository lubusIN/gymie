<?php

use App\Enums\Status;
use App\Filament\Resources\Enquiries\Pages\ListEnquiries;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Resources\FollowUps\Pages\ListFollowUps;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Plans\Pages\ListPlans;
use App\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Enquiry;
use App\Models\Expense;
use App\Models\FollowUp;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Filament\StatusAction;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('super_admin', 'web');
    $this->actingAs(User::factory()->create()->assignRole('super_admin'));
    Filament::setCurrentPanel('admin');
});

it('keeps status row and confirmation modal icons aligned', function (
    Status $status,
    Heroicon $icon,
): void {
    $action = StatusAction::make('change_status', $status);

    expect($action->getIcon())->toBe($icon)
        ->and($action->getModalIcon())->toBe($icon);
})->with([
    'active' => [
        Status::Active,
        Heroicon::OutlinedCheckCircle,
    ],
    'inactive' => [
        Status::Inactive,
        Heroicon::OutlinedXCircle,
    ],
    'expiring' => [
        Status::Expiring,
        Heroicon::OutlinedClock,
    ],
    'expired' => [
        Status::Expired,
        Heroicon::OutlinedXCircle,
    ],
    'lost' => [
        Status::Lost,
        Heroicon::OutlinedXCircle,
    ],
    'done' => [
        Status::Done,
        Heroicon::OutlinedCheckCircle,
    ],
    'cancelled' => [
        Status::Cancelled,
        Heroicon::OutlinedXCircle,
    ],
    'paid' => [
        Status::Paid,
        Heroicon::OutlinedCheckCircle,
    ],
    'pending' => [
        Status::Pending,
        Heroicon::OutlinedClock,
    ],
    'overdue' => [
        Status::Overdue,
        Heroicon::OutlinedExclamationTriangle,
    ],
]);

it('rejects statuses without an action icon', function (): void {
    expect(fn () => StatusAction::make('issue', Status::Issued))
        ->toThrow(InvalidArgumentException::class, 'Status [issued] does not have an action icon.');
});

it('uses semantic icons for status row actions', function (
    string $page,
    Closure $createRecord,
    string $action,
    Heroicon $icon,
): void {
    /** @var Model $record */
    $record = $createRecord();

    $component = Livewire::test($page)
        ->assertCanSeeTableRecords([$record])
        ->assertTableActionVisible($action, $record)
        ->assertTableActionHasIcon($action, $icon, $record);

    expect($component->instance()->getTable()->getAction($action)?->getModalIcon())->toBe($icon);
})->with([
    'activate member' => [
        ListMembers::class,
        fn (): Member => Member::factory()->create(['status' => 'inactive']),
        'mark_as_active',
        Heroicon::OutlinedCheckCircle,
    ],
    'deactivate member' => [
        ListMembers::class,
        fn (): Member => Member::factory()->create(['status' => 'active']),
        'mark_as_inactive',
        Heroicon::OutlinedXCircle,
    ],
    'activate plan' => [
        ListPlans::class,
        fn (): Plan => Plan::factory()->create(['status' => 'inactive']),
        'mark_as_active',
        Heroicon::OutlinedCheckCircle,
    ],
    'deactivate plan' => [
        ListPlans::class,
        fn (): Plan => Plan::factory()->create(['status' => 'active']),
        'mark_as_inactive',
        Heroicon::OutlinedXCircle,
    ],
    'mark subscription as expiring' => [
        ListSubscriptions::class,
        fn (): Subscription => Subscription::factory()->create(['status' => 'ongoing']),
        'mark_expiring',
        Heroicon::OutlinedClock,
    ],
    'mark subscription as expired' => [
        ListSubscriptions::class,
        fn (): Subscription => Subscription::factory()->create(['status' => 'expiring']),
        'mark_expired',
        Heroicon::OutlinedXCircle,
    ],
    'activate user' => [
        ListUsers::class,
        fn (): User => User::factory()->create(['status' => 'inactive']),
        'active',
        Heroicon::OutlinedCheckCircle,
    ],
    'deactivate user' => [
        ListUsers::class,
        fn (): User => User::factory()->create(['status' => 'active']),
        'inactive',
        Heroicon::OutlinedXCircle,
    ],
    'mark enquiry as lost' => [
        ListEnquiries::class,
        fn (): Enquiry => Enquiry::factory()->create(['status' => 'lead']),
        'mark_as_lost',
        Heroicon::OutlinedXCircle,
    ],
    'mark follow-up as done' => [
        ListFollowUps::class,
        fn (): FollowUp => FollowUp::factory()->create(['status' => 'pending']),
        'mark_as_done',
        Heroicon::OutlinedCheckCircle,
    ],
    'cancel invoice' => [
        ListInvoices::class,
        fn (): Invoice => Invoice::factory()->create([
            'status' => 'issued',
            'paid_amount' => 0,
        ]),
        'cancel_invoice',
        Heroicon::OutlinedXCircle,
    ],
    'mark expense as paid' => [
        ListExpenses::class,
        fn (): Expense => Expense::factory()->create(['status' => 'pending']),
        'mark_as_paid',
        Heroicon::OutlinedCheckCircle,
    ],
    'mark expense as overdue' => [
        ListExpenses::class,
        fn (): Expense => Expense::factory()->create(['status' => 'pending']),
        'mark_as_overdue',
        Heroicon::OutlinedExclamationTriangle,
    ],
    'mark expense as pending' => [
        ListExpenses::class,
        fn (): Expense => Expense::factory()->create(['status' => 'overdue']),
        'mark_as_pending',
        Heroicon::OutlinedClock,
    ],
    'mark expense as cancelled' => [
        ListExpenses::class,
        fn (): Expense => Expense::factory()->create(['status' => 'pending']),
        'mark_as_cancelled',
        Heroicon::OutlinedXCircle,
    ],
]);

it('updates expense statuses from row actions', function (
    string $sourceStatus,
    string $action,
    Status $targetStatus,
    bool $hasPaidAt,
): void {
    $expense = Expense::factory()->create([
        'status' => $sourceStatus,
        'paid_at' => null,
    ]);

    Livewire::test(ListExpenses::class)
        ->callTableAction($action, $expense)
        ->assertNotified();

    $expense->refresh();

    expect($expense->status)->toBe($targetStatus)
        ->and($expense->paid_at !== null)->toBe($hasPaidAt);
})->with([
    'paid' => ['pending', 'mark_as_paid', Status::Paid, true],
    'overdue' => ['pending', 'mark_as_overdue', Status::Overdue, false],
    'pending' => ['overdue', 'mark_as_pending', Status::Pending, false],
    'cancelled' => ['pending', 'mark_as_cancelled', Status::Cancelled, false],
]);

it('keeps expense status and record actions available after grouping', function (): void {
    $expense = Expense::factory()->create(['status' => 'pending']);

    Livewire::test(ListExpenses::class)
        ->assertTableActionVisible('mark_as_paid', $expense)
        ->assertTableActionVisible('view', $expense)
        ->assertTableActionVisible('edit', $expense)
        ->assertTableActionVisible('delete', $expense);
});
