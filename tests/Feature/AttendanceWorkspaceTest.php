<?php

use App\Enums\Status;
use App\Filament\Resources\Attendances\Pages\ListAttendances;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\RelationManagers\AttendancesRelationManager;
use App\Filament\Widgets\AttendanceOverviewWidget;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AttendanceService;
use Database\Seeders\AttendanceSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
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

it('records a visit for an active member', function (): void {
    $member = Member::factory()->create(['status' => Status::Active]);
    $subscription = Subscription::factory()->for($member)->current()->create();
    $service = app(AttendanceService::class);

    $attendance = $service->checkIn(
        $member,
        $this->user,
        Carbon::parse('2026-09-28 08:00:00'),
    );

    expect($attendance->subscription_id)->toBe($subscription->id)
        ->and($attendance->checked_in_by_id)->toBe($this->user->id)
        ->and($attendance->visited_on->toDateString())->toBe('2026-09-28');
});

it('records at most one visit per member per business day', function (): void {
    $member = Member::factory()->create(['status' => Status::Active]);
    Subscription::factory()->for($member)->current()->create();
    $service = app(AttendanceService::class);
    $service->checkIn($member, $this->user, Carbon::parse('2026-09-28 08:00:00'));

    expect(fn () => $service->checkIn(
        $member,
        $this->user,
        Carbon::parse('2026-09-28 08:05:00'),
    ))->toThrow(ValidationException::class);

    $nextDayVisit = $service->checkIn(
        $member,
        $this->user,
        Carbon::parse('2026-09-29 08:00:00'),
    );

    expect(fn () => $nextDayVisit->update([
        'checked_in_at' => Carbon::parse('2026-09-28 09:00:00'),
    ]))->toThrow(ValidationException::class);

    expect(Attendance::query()->whereBelongsTo($member)->count())->toBe(2);
});

it('seeds overlapping subscriptions without duplicating member visit dates', function (): void {
    $this->travelTo('2026-09-28 12:00:00');
    $member = Member::factory()->create(['status' => Status::Active]);
    Subscription::factory()->count(2)->for($member)->current()->create();

    $this->seed(AttendanceSeeder::class);
    $this->seed(AttendanceSeeder::class);

    $attendances = Attendance::query()->whereBelongsTo($member)->get();

    expect($attendances)->toHaveCount(3)
        ->and($attendances->pluck('visited_on')->unique()->count())->toBe(3);
});

it('prevents inactive members from checking in', function (): void {
    $member = Member::factory()->create(['status' => Status::Inactive]);

    expect(fn () => app(AttendanceService::class)->checkIn(
        $member,
        $this->user,
        Carbon::parse('2026-09-28 08:00:00'),
    ))->toThrow(DomainException::class, 'Only active members');

    expect(Attendance::query()->exists())->toBeFalse();
});

it('requires a current membership', function (): void {
    $member = Member::factory()->create(['status' => Status::Active]);
    $service = app(AttendanceService::class);

    expect(fn () => $service->checkIn(
        $member,
        $this->user,
        Carbon::parse('2026-09-28 08:00:00'),
    ))->toThrow(DomainException::class, 'current membership');

    expect(Attendance::query()->exists())->toBeFalse();
});

it('records member visits from the attendance workspace', function (): void {
    $member = Member::factory()->create(['status' => Status::Active]);
    Subscription::factory()->for($member)->current()->create();

    Livewire::test(ListAttendances::class)
        ->callAction('check_in', [
            'member_id' => $member->id,
            'checked_in_at' => '2026-09-28 08:00:00',
        ])
        ->assertHasNoActionErrors();

    $attendance = Attendance::query()->whereBelongsTo($member)->firstOrFail();

    expect($attendance->checked_in_at->format('Y-m-d H:i:s'))->toBe('2026-09-28 08:00:00');
});

it('lists active members with expired memberships in the manual check-in selector', function (): void {
    $expiredMember = Member::factory()->create([
        'code' => 'GY-EXPIRED-101',
        'status' => Status::Active,
    ]);
    Subscription::factory()->for($expiredMember)->create([
        'start_date' => '2026-08-01',
        'end_date' => '2026-09-27',
        'status' => Status::Expired,
    ]);
    $inactiveMember = Member::factory()->create([
        'code' => 'GY-INACTIVE-101',
        'status' => Status::Inactive,
    ]);

    Livewire::test(ListAttendances::class)
        ->mountAction('check_in')
        ->assertSchemaComponentExists(
            'member_id',
            checkComponentUsing: function (Select $component) use ($expiredMember, $inactiveMember): bool {
                $initialOptions = $component->getOptions();
                $expiredSearchResults = $component->getSearchResults($expiredMember->code);
                $inactiveSearchResults = $component->getSearchResults($inactiveMember->code);

                return ($initialOptions[$expiredMember->getKey()] ?? null) === "{$expiredMember->code} · {$expiredMember->name}"
                    && ($expiredSearchResults[$expiredMember->getKey()] ?? null) === "{$expiredMember->code} · {$expiredMember->name}"
                    && ! array_key_exists($inactiveMember->getKey(), $inactiveSearchResults);
            },
        );
});

it('rejects a manual check-in for an expired membership', function (): void {
    $this->travelTo('2026-09-29 08:30:00');
    $member = Member::factory()->create(['status' => Status::Active]);
    Subscription::factory()->for($member)->create([
        'start_date' => '2026-08-01',
        'end_date' => '2026-09-28',
        'status' => Status::Expired,
    ]);

    Livewire::test(ListAttendances::class)
        ->callAction('check_in', [
            'member_id' => $member->getKey(),
            'checked_in_at' => '2026-09-29 08:30:00',
        ])
        ->assertNotified();

    expect(Attendance::query()->whereBelongsTo($member)->exists())->toBeFalse();
});

it('does not show a plan column in the attendance table', function (): void {
    Livewire::test(ListAttendances::class)
        ->assertTableColumnDoesNotExist('subscription.plan.name');
});

it('does not expose view or edit actions in the attendance table', function (): void {
    Livewire::test(ListAttendances::class)
        ->assertTableActionDoesNotExist('view')
        ->assertTableActionDoesNotExist('edit');
});

it('shows attendance history on the member profile', function (): void {
    $member = Member::factory()->create();
    $attendance = Attendance::factory()->for($member)->create();

    Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
        ->assertSchemaComponentExists('member_sections.attendance_tab');

    Livewire::test(AttendancesRelationManager::class, [
        'ownerRecord' => $member,
        'pageClass' => ViewMember::class,
    ])->assertCanSeeTableRecords([$attendance]);
});

it('shows a downloadable shared attendance QR code', function (): void {
    Livewire::test(ListAttendances::class)
        ->mountAction('attendance_qr')
        ->assertActionMounted('attendance_qr')
        ->assertMountedActionModalSeeHtml('data:image/svg+xml;base64,')
        ->assertMountedActionModalSee(__('app.attendance.qr_description'));
});

it('filters members who need attendance follow-up', function (): void {
    $this->travelTo('2026-09-28 12:00:00');

    $recentMember = Member::factory()->create();
    Attendance::factory()->for($recentMember)->create([
        'checked_in_at' => '2026-09-25 08:00:00',
    ]);

    $inactiveMember = Member::factory()->create();
    Attendance::factory()->for($inactiveMember)->create([
        'checked_in_at' => '2026-09-01 08:00:00',
    ]);

    $neverVisitedMember = Member::factory()->create();

    Livewire::test(ListMembers::class)
        ->filterTable('attendance_recency', '14')
        ->assertCanSeeTableRecords([$inactiveMember, $neverVisitedMember])
        ->assertCanNotSeeTableRecords([$recentMember]);

    Livewire::test(ListMembers::class)
        ->filterTable('attendance_recency', 'never')
        ->assertCanSeeTableRecords([$neverVisitedMember])
        ->assertCanNotSeeTableRecords([$recentMember, $inactiveMember]);
});

it('summarizes attendance for the selected period', function (): void {
    $this->travelTo('2026-09-28 12:00:00');
    $recentMember = Member::factory()->create(['status' => Status::Active]);
    Attendance::factory()->for($recentMember)->create([
        'checked_in_at' => '2026-09-28 08:00:00',
    ]);
    Attendance::factory()->for($recentMember)->create([
        'checked_in_at' => '2026-09-27 10:00:00',
    ]);
    Attendance::factory()->for(Member::factory()->create(['status' => Status::Active]))->create([
        'checked_in_at' => '2026-09-20 08:00:00',
    ]);
    Member::factory()->create(['status' => Status::Active]);

    Livewire::test(AttendanceOverviewWidget::class, [
        'pageFilters' => [
            'period' => 'custom',
            'startDate' => '2026-09-22',
            'endDate' => '2026-09-28',
        ],
    ])
        ->assertSee('2')
        ->assertSee('1')
        ->assertSee(__('app.widgets.total_check_ins'))
        ->assertSee(__('app.widgets.unique_members'))
        ->assertSee(__('app.widgets.members_without_visits'));
});

it('shows the attendance overview on the attendance page', function (): void {
    $page = Livewire::test(ListAttendances::class)->instance();

    expect($page->getVisibleHeaderWidgets())
        ->toContain(AttendanceOverviewWidget::class);
});

it('filters member attendance with the page period selector', function (): void {
    $this->travelTo('2026-10-15 12:00:00');

    $recentAttendance = Attendance::factory()->create([
        'checked_in_at' => '2026-10-14 08:00:00',
    ]);
    $todayAttendance = Attendance::factory()->create([
        'checked_in_at' => '2026-10-15 08:00:00',
    ]);
    $olderAttendance = Attendance::factory()->create([
        'checked_in_at' => '2026-09-20 08:00:00',
    ]);

    Livewire::test(ListAttendances::class)
        ->assertSet('filters.period', 'today')
        ->assertSet('filters.startDate', '2026-10-15')
        ->assertSet('filters.endDate', '2026-10-15')
        ->assertCanSeeTableRecords([$todayAttendance])
        ->assertCanNotSeeTableRecords([$recentAttendance, $olderAttendance])
        ->call('setPeriod', 'all')
        ->assertCanSeeTableRecords([$todayAttendance, $recentAttendance, $olderAttendance]);
});

it('hides attendance write actions from users without write permission', function (): void {
    $role = Role::findOrCreate('attendance_viewer', 'web');
    $role->givePermissionTo([
        Permission::findOrCreate('ViewAny:Attendance', 'web'),
        Permission::findOrCreate('View:Attendance', 'web'),
    ]);
    $this->actingAs(User::factory()->create()->assignRole($role));
    Livewire::test(ListAttendances::class)
        ->assertActionHidden('check_in');
});
