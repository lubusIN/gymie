<?php

use App\Enums\Status;
use App\Filament\Resources\StaffAttendances\Pages\ListStaffAttendances;
use App\Filament\Resources\StaffAttendances\StaffAttendanceResource;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\StaffAttendancesRelationManager;
use App\Models\StaffAttendance;
use App\Models\User;
use App\Services\AttendanceService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('super_admin', 'web');
    $this->user = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($this->user);
    Filament::setCurrentPanel('admin');
});

it('records one attendance per active staff member and business day', function (): void {
    $staff = User::factory()->create(['status' => Status::Active]);
    $service = app(AttendanceService::class);

    $attendance = $service->checkInStaff(
        $staff,
        $this->user,
        Carbon::parse('2026-10-07 08:00:00'),
    );

    expect($attendance->staff_id)->toBe($staff->id)
        ->and($attendance->checked_in_by_id)->toBe($this->user->id)
        ->and($attendance->visited_on->toDateString())->toBe('2026-10-07');

    expect(fn () => $service->checkInStaff(
        $staff,
        $this->user,
        Carbon::parse('2026-10-07 09:00:00'),
    ))->toThrow(ValidationException::class);

    expect(StaffAttendance::query()->whereBelongsTo($staff, 'staff')->count())->toBe(1);
});

it('rejects attendance for inactive staff', function (): void {
    $staff = User::factory()->inactive()->create();

    expect(fn () => app(AttendanceService::class)->checkInStaff(
        $staff,
        $this->user,
        Carbon::parse('2026-10-07 08:00:00'),
    ))->toThrow(DomainException::class, 'Only active staff');

    expect(StaffAttendance::query()->exists())->toBeFalse();
});

it('records staff attendance from the attendance workspace', function (): void {
    $staff = User::factory()->create(['status' => Status::Active]);

    Livewire::test(ListStaffAttendances::class)
        ->callAction('check_in_staff', [
            'staff_id' => $staff->id,
            'checked_in_at' => '2026-10-07 08:00:00',
        ])
        ->assertHasNoActionErrors();

    $attendance = StaffAttendance::query()->whereBelongsTo($staff, 'staff')->sole();
    expect($attendance->checked_in_at->format('Y-m-d H:i:s'))->toBe('2026-10-07 08:00:00');
});

it('shows staff attendance on the individual staff profile', function (): void {
    $staff = User::factory()->create();
    $attendance = StaffAttendance::factory()->for($staff, 'staff')->create();

    Livewire::test(ViewUser::class, ['record' => $staff->getRouteKey()])
        ->assertSchemaComponentExists('user_sections.attendance_tab');

    Livewire::test(StaffAttendancesRelationManager::class, [
        'ownerRecord' => $staff,
        'pageClass' => ViewUser::class,
    ])->assertCanSeeTableRecords([$attendance]);
});

it('keeps staff attendance under the existing attendance navigation', function (): void {
    expect(StaffAttendanceResource::shouldRegisterNavigation())->toBeFalse();

    Livewire::test(ListStaffAttendances::class)
        ->assertSee(__('app.attendance.members'))
        ->assertSee(__('app.attendance.staff'));
});

it('hides staff check-in from users without attendance write permission', function (): void {
    $role = Role::findOrCreate('attendance_viewer', 'web');
    $role->givePermissionTo([
        Permission::findOrCreate('ViewAny:Attendance', 'web'),
        Permission::findOrCreate('View:Attendance', 'web'),
    ]);
    $this->actingAs(User::factory()->create()->assignRole($role));

    Livewire::test(ListStaffAttendances::class)
        ->assertActionHidden('check_in_staff');
});
