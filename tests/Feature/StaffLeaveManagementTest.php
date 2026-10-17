<?php

use App\Enums\StaffLeaveType;
use App\Enums\Status;
use App\Filament\Resources\StaffLeaves\Pages\CreateStaffLeave;
use App\Filament\Resources\StaffLeaves\Pages\EditStaffLeave;
use App\Filament\Resources\StaffLeaves\Pages\ListStaffLeaves;
use App\Filament\Resources\StaffLeaves\Pages\ViewStaffLeave;
use App\Filament\Resources\StaffLeaves\StaffLeaveResource;
use App\Models\StaffActivity;
use App\Models\StaffLeave;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('lets staff submit their own leave request', function (): void {
    $staff = User::factory()->create();
    $otherStaff = User::factory()->create();
    $this->actingAs($staff);

    Livewire::test(CreateStaffLeave::class)
        ->fillForm([
            'staff_id' => $otherStaff->getKey(),
            'type' => StaffLeaveType::Sick->value,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-03',
            'reason' => 'Doctor advised rest.',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    $leave = StaffLeave::query()->sole();

    expect($leave->staff_id)->toBe($staff->getKey())
        ->and($leave->status)->toBe(Status::Pending)
        ->and($leave->durationInDays())->toBe(2);
});

it('prevents overlapping pending or approved leave requests', function (): void {
    $staff = User::factory()->create();
    $this->actingAs($staff);

    StaffLeave::factory()->for($staff, 'staff')->create([
        'start_date' => '2026-11-02',
        'end_date' => '2026-11-04',
        'status' => Status::Pending,
    ]);

    Livewire::test(CreateStaffLeave::class)
        ->fillForm([
            'type' => StaffLeaveType::Casual->value,
            'start_date' => '2026-11-04',
            'end_date' => '2026-11-05',
            'reason' => 'Family commitment.',
        ])
        ->call('create')
        ->assertHasErrors(['start_date']);

    expect(StaffLeave::query()->count())->toBe(1);
});

it('lets a manager approve another staff members request', function (): void {
    Role::findOrCreate('super_admin', 'web');
    $manager = User::factory()->create()->assignRole('super_admin');
    $staff = User::factory()->create();
    $leave = StaffLeave::factory()->for($staff, 'staff')->create([
        'status' => Status::Pending,
    ]);
    $this->actingAs($manager);
    LaravelNotification::fake();

    Livewire::test(ListStaffLeaves::class)
        ->assertTableActionHidden('cancel_leave', $leave)
        ->callTableAction('approve', $leave)
        ->assertHasNoTableActionErrors()
        ->assertNotified();

    $leave->refresh();

    expect($leave->status)->toBe(Status::Approved)
        ->and($leave->reviewed_by_id)->toBe($manager->getKey())
        ->and($leave->reviewed_at)->not->toBeNull()
        ->and(StaffActivity::query()
            ->where('subject_type', StaffLeave::class)
            ->where('subject_id', $leave->getKey())
            ->where('causer_id', $manager->getKey())
            ->where('event', 'updated')
            ->exists())->toBeTrue();

    LaravelNotification::assertSentTo(
        $staff,
        DatabaseNotification::class,
        fn (DatabaseNotification $notification): bool => $notification->data['title'] === __('app.notifications.staff_leave_approved'),
    );
});

it('notifies staff when a manager rejects their request', function (): void {
    Role::findOrCreate('super_admin', 'web');
    $manager = User::factory()->create()->assignRole('super_admin');
    $staff = User::factory()->create();
    $leave = StaffLeave::factory()->for($staff, 'staff')->create();
    $this->actingAs($manager);
    LaravelNotification::fake();

    Livewire::test(ListStaffLeaves::class)
        ->callTableAction('reject', $leave, [
            'review_note' => 'Coverage is unavailable.',
        ])
        ->assertHasNoTableActionErrors()
        ->assertNotified();

    expect($leave->refresh()->status)->toBe(Status::Rejected)
        ->and($leave->review_note)->toBe('Coverage is unavailable.');

    LaravelNotification::assertSentTo(
        $staff,
        DatabaseNotification::class,
        fn (DatabaseNotification $notification): bool => $notification->data['title'] === __('app.notifications.staff_leave_rejected'),
    );
});

it('lets staff cancel their pending request with an audit reason', function (): void {
    $staff = User::factory()->create();
    $leave = StaffLeave::factory()->for($staff, 'staff')->create();
    $this->actingAs($staff);
    LaravelNotification::fake();

    Livewire::test(ListStaffLeaves::class)
        ->callTableAction('cancel_leave', $leave, [
            'cancellation_reason' => 'The appointment was moved.',
        ])
        ->assertHasNoTableActionErrors()
        ->assertNotified();

    $leave->refresh();

    expect($leave->status)->toBe(Status::Cancelled)
        ->and($leave->cancelled_by_id)->toBe($staff->getKey())
        ->and($leave->cancelled_at)->not->toBeNull()
        ->and($leave->cancellation_reason)->toBe('The appointment was moved.');

    LaravelNotification::assertNothingSent();
});

it('lets a manager cancel approved leave and notifies the staff member', function (): void {
    Role::findOrCreate('super_admin', 'web');
    $manager = User::factory()->create()->assignRole('super_admin');
    $staff = User::factory()->create();
    $leave = StaffLeave::factory()->for($staff, 'staff')->create([
        'status' => Status::Approved,
        'reviewed_by_id' => $manager->getKey(),
        'reviewed_at' => now(),
    ]);
    $this->actingAs($manager);
    LaravelNotification::fake();

    Livewire::test(ListStaffLeaves::class)
        ->callTableAction('cancel_leave', $leave, [
            'cancellation_reason' => 'The gym requires coverage that day.',
        ])
        ->assertHasNoTableActionErrors()
        ->assertNotified();

    $leave->refresh();

    expect($leave->status)->toBe(Status::Cancelled)
        ->and($leave->cancelled_by_id)->toBe($manager->getKey())
        ->and($leave->cancelled_at)->not->toBeNull()
        ->and($leave->cancellation_reason)->toBe('The gym requires coverage that day.');

    LaravelNotification::assertSentTo(
        $staff,
        DatabaseNotification::class,
        fn (DatabaseNotification $notification): bool => $notification->data['title'] === __('app.notifications.staff_leave_cancelled'),
    );
});

it('finds approved staff leave that overlaps an availability date range', function (): void {
    Role::findOrCreate('super_admin', 'web');
    $manager = User::factory()->create()->assignRole('super_admin');
    $overlappingLeave = StaffLeave::factory()->create([
        'status' => Status::Approved,
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-10',
    ]);
    $laterLeave = StaffLeave::factory()->create([
        'status' => Status::Approved,
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-22',
    ]);
    $this->actingAs($manager);

    Livewire::test(ListStaffLeaves::class)
        ->set('activeTab', Status::Approved->value)
        ->filterTable('date', [
            'date_from' => '2026-11-08',
            'date_to' => '2026-11-12',
        ])
        ->assertCanSeeTableRecords([$overlappingLeave])
        ->assertCanNotSeeTableRecords([$laterLeave]);
});

it('scopes requests to the signed in staff member and prevents self approval', function (): void {
    $staff = User::factory()->create();
    $ownLeave = StaffLeave::factory()->for($staff, 'staff')->create([
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-02',
    ]);
    $ownApprovedLeave = StaffLeave::factory()->for($staff, 'staff')->create([
        'status' => Status::Approved,
        'start_date' => '2026-11-10',
        'end_date' => '2026-11-11',
    ]);
    $otherLeave = StaffLeave::factory()->create();
    $this->actingAs($staff);

    Livewire::test(ListStaffLeaves::class)
        ->assertCanSeeTableRecords([$ownLeave, $ownApprovedLeave])
        ->assertCanNotSeeTableRecords([$otherLeave])
        ->assertTableActionHidden('approve', $ownLeave)
        ->assertTableActionHidden('reject', $ownLeave)
        ->assertTableActionVisible('cancel_leave', $ownLeave)
        ->assertTableActionHidden('cancel_leave', $ownApprovedLeave);
});

it('shows only actions valid for each leave state even to super admins', function (): void {
    Role::findOrCreate('super_admin', 'web');
    $manager = User::factory()->create()->assignRole('super_admin');
    $pendingLeave = StaffLeave::factory()->create(['status' => Status::Pending]);
    $approvedLeave = StaffLeave::factory()->create(['status' => Status::Approved]);
    $rejectedLeave = StaffLeave::factory()->create(['status' => Status::Rejected]);
    $cancelledLeave = StaffLeave::factory()->create(['status' => Status::Cancelled]);
    $this->actingAs($manager);

    Livewire::test(ListStaffLeaves::class)
        ->assertTableActionVisible('approve', $pendingLeave)
        ->assertTableActionVisible('reject', $pendingLeave)
        ->assertTableActionHidden('cancel_leave', $pendingLeave)
        ->assertTableActionHidden('approve', $approvedLeave)
        ->assertTableActionHidden('reject', $approvedLeave)
        ->assertTableActionVisible('cancel_leave', $approvedLeave)
        ->assertTableActionHidden('edit', $approvedLeave)
        ->assertTableActionHidden('delete', $approvedLeave)
        ->assertTableActionHidden('approve', $rejectedLeave)
        ->assertTableActionHidden('reject', $rejectedLeave)
        ->assertTableActionHidden('cancel_leave', $rejectedLeave)
        ->assertTableActionHidden('edit', $rejectedLeave)
        ->assertTableActionHidden('delete', $rejectedLeave)
        ->assertTableActionHidden('approve', $cancelledLeave)
        ->assertTableActionHidden('reject', $cancelledLeave)
        ->assertTableActionHidden('cancel_leave', $cancelledLeave)
        ->assertTableActionHidden('edit', $cancelledLeave)
        ->assertTableActionHidden('delete', $cancelledLeave);

    Livewire::test(ViewStaffLeave::class, ['record' => $cancelledLeave->getRouteKey()])
        ->assertActionHidden('approve')
        ->assertActionHidden('reject')
        ->assertActionHidden('cancel_leave')
        ->assertActionHidden('edit')
        ->assertActionHidden('delete');

    $this->get(StaffLeaveResource::getUrl('edit', ['record' => $approvedLeave]))
        ->assertForbidden();
});

it('prevents staff from reassigning their request while editing it', function (): void {
    $staff = User::factory()->create();
    $otherStaff = User::factory()->create();
    $leave = StaffLeave::factory()->for($staff, 'staff')->create();
    $this->actingAs($staff);

    Livewire::test(EditStaffLeave::class, ['record' => $leave->getRouteKey()])
        ->fillForm(['staff_id' => $otherStaff->getKey()])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($leave->refresh()->staff_id)->toBe($staff->getKey());
});
