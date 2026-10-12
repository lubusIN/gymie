<?php

use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\StaffActivitiesRelationManager;
use App\Models\Expense;
use App\Models\Member;
use App\Models\StaffActivity;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('records safe before and after values for authenticated staff changes', function (): void {
    $staff = User::factory()->create();
    $this->actingAs($staff);

    $member = Member::factory()->create(['name' => 'Original Name']);
    $member->update(['name' => 'Updated Name']);

    $activity = StaffActivity::query()
        ->staff()
        ->where('subject_type', Member::class)
        ->where('subject_id', $member->getKey())
        ->where('event', 'updated')
        ->sole();

    expect($activity->causer->is($staff))->toBeTrue()
        ->and($activity->oldValues()->all())->toBe(['name' => 'Original Name'])
        ->and($activity->newValues()->all())->toBe(['name' => 'Updated Name']);
});

it('never stores passwords in staff activity', function (): void {
    $staff = User::factory()->create();
    $target = User::factory()->create();
    $this->actingAs($staff);

    $target->update([
        'name' => 'Updated Staff Name',
        'password' => Hash::make('a-new-password'),
    ]);

    $activity = StaffActivity::query()
        ->staff()
        ->where('subject_type', User::class)
        ->where('subject_id', $target->getKey())
        ->where('event', 'updated')
        ->sole();
    $changes = $activity->attribute_changes->toArray();

    expect(data_get($changes, 'attributes.name'))->toBe('Updated Staff Name')
        ->and(data_get($changes, 'attributes'))->not->toHaveKey('password')
        ->and(data_get($changes, 'old'))->not->toHaveKey('password');
});

it('does not present background model changes as staff activity', function (): void {
    Expense::factory()->create();

    expect(StaffActivity::query()->staff()->count())->toBe(0);
});

it('protects the staff activity resource with dedicated permissions', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)
        ->get('/staff-activities')
        ->assertForbidden();

    Permission::findOrCreate('ViewAny:StaffActivity', 'web');
    $user->givePermissionTo('ViewAny:StaffActivity');

    $this->actingAs($user)
        ->get('/staff-activities')
        ->assertSuccessful();
});

it('shows the activity tab on a staff profile only to authorized viewers', function (): void {
    Permission::findOrCreate('ViewAny:User', 'web');
    Permission::findOrCreate('View:User', 'web');
    Permission::findOrCreate('ViewAny:StaffActivity', 'web');
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['ViewAny:User', 'View:User']);
    $staff = User::factory()->create();
    $this->actingAs($viewer);
    Filament::setCurrentPanel('admin');

    expect(StaffActivitiesRelationManager::canViewForRecord($staff, ViewUser::class))->toBeFalse();

    $viewer->givePermissionTo('ViewAny:StaffActivity');

    expect(StaffActivitiesRelationManager::canViewForRecord($staff, ViewUser::class))->toBeTrue();

    Livewire::test(ViewUser::class, ['record' => $staff->getRouteKey()])
        ->assertSchemaComponentExists('user_sections.details_tab')
        ->assertSchemaComponentExists('user_sections.activity_tab');
});

it('shows only activity performed by the selected staff member', function (): void {
    Permission::findOrCreate('ViewAny:StaffActivity', 'web');
    Permission::findOrCreate('View:StaffActivity', 'web');
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['ViewAny:StaffActivity', 'View:StaffActivity']);
    $selectedStaff = User::factory()->create();
    $otherStaff = User::factory()->create();

    $this->actingAs($selectedStaff);
    $selectedMember = Member::factory()->create();
    $selectedActivity = StaffActivity::query()->staff()->forSubject($selectedMember)->sole();

    $this->actingAs($otherStaff);
    $otherMember = Member::factory()->create();
    $otherActivity = StaffActivity::query()->staff()->forSubject($otherMember)->sole();

    $this->actingAs($viewer);
    Filament::setCurrentPanel('admin');

    Livewire::test(StaffActivitiesRelationManager::class, [
        'ownerRecord' => $selectedStaff,
        'pageClass' => ViewUser::class,
    ])
        ->assertCanSeeTableRecords([$selectedActivity])
        ->assertCanNotSeeTableRecords([$otherActivity]);
});

it('links an activity subject only when the viewer may open it', function (): void {
    Permission::findOrCreate('View:Member', 'web');
    $staff = User::factory()->create();
    $viewer = User::factory()->create();
    $this->actingAs($staff);
    $member = Member::factory()->create();
    $activity = StaffActivity::query()->staff()->forSubject($member)->sole();

    $this->actingAs($viewer);

    expect($activity->subjectUrl())->toBeNull();

    $viewer->givePermissionTo('View:Member');

    expect($activity->subjectUrl())->toContain("/members/{$member->getRouteKey()}");
});
