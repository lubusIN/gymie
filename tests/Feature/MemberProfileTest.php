<?php

use App\Enums\MemberActivityType;
use App\Enums\Status;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\RelationManagers\SubscriptionsRelationManager;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('super_admin', 'web');
    $this->actingAs(User::factory()->create()->assignRole('super_admin'));
    Filament::setCurrentPanel('admin');
});

it('organizes member details, activity, and subscriptions into tabs', function (): void {
    $member = Member::factory()->create();

    Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
        ->assertSchemaComponentExists('member_sections.details_tab')
        ->assertSchemaComponentExists('member_sections.activity_tab')
        ->assertSchemaComponentExists('member_sections.subscriptions_tab');
});

it('shows member subscriptions through the relation manager', function (): void {
    $member = Member::factory()->create();
    $subscription = Subscription::factory()->for($member)->create();

    Livewire::test(SubscriptionsRelationManager::class, [
        'ownerRecord' => $member,
        'pageClass' => ViewMember::class,
    ])->assertCanSeeTableRecords([$subscription]);
});

it('records member and subscription lifecycle activity', function (): void {
    $member = Member::factory()->create([
        'status' => Status::Active,
        'source' => 'promotions',
    ]);

    $member->update([
        'status' => Status::Inactive,
        'source' => 'word_of_mouth',
    ]);

    $plan = Plan::factory()->create(['name' => 'Strength']);
    $subscription = Subscription::factory()->for($member)->for($plan)->create([
        'status' => Status::Ongoing,
    ]);
    $subscription->update(['status' => Status::Expired]);

    expect(Activity::query()->forSubject($member)->forEvent(MemberActivityType::MemberCreated->value)->count())->toBe(1)
        ->and(Activity::query()->forSubject($member)->forEvent(MemberActivityType::MemberUpdated->value)->count())->toBe(1)
        ->and(Activity::query()->forSubject($member)->forEvent(MemberActivityType::MemberStatusChanged->value)->count())->toBe(1)
        ->and(Activity::query()->forSubject($member)->forEvent(MemberActivityType::SubscriptionCreated->value)->count())->toBe(1)
        ->and(Activity::query()->forSubject($member)->forEvent(MemberActivityType::SubscriptionStatusChanged->value)->count())->toBe(1);

    Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
        ->assertSee('created this member')
        ->assertSee('changed the member status from Active to Inactive')
        ->assertSee('added a Strength subscription')
        ->assertSee('changed the Strength subscription from Ongoing to Expired');
});
