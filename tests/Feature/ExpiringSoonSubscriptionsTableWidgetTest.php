<?php

use App\Filament\Widgets\Analytics\MembershipOverviewSubscriptionsTableWidget;
use App\Helpers\Helpers;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Helpers::setTestSettingsOverride(null);
});

it('lists only subscriptions that are ending within the expiring window', function (): void {
    config()->set('app.timezone', 'UTC');
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-02-23', 'UTC'));

    Helpers::setTestSettingsOverride([
        'general' => [
            'currency' => 'INR',
        ],
        'subscriptions' => [
            'expiring_days' => 7,
        ],
    ]);

    $member = Member::factory()->create();
    $plan = Plan::factory()->create();

    $included = Subscription::factory()->create([
        'member_id' => $member->id,
        'plan_id' => $plan->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-02-26',
    ]);

    Subscription::factory()->create([
        'member_id' => $member->id,
        'plan_id' => $plan->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-03-10',
    ]);

    Subscription::factory()->create([
        'member_id' => $member->id,
        'plan_id' => $plan->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-02-20',
    ]);

    Subscription::factory()->create([
        'member_id' => $member->id,
        'plan_id' => $plan->id,
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
    ]);

    $widget = new MembershipOverviewSubscriptionsTableWidget;
    $widget->activeTab = 'expiring';

    $method = new ReflectionMethod($widget, 'getActiveTabQuery');
    $method->setAccessible(true);

    /** @var Builder $query */
    $query = $method->invoke($widget);

    $ids = $query->pluck('id')->all();

    expect($ids)->toBe([$included->id]);
});

it('lists only subscriptions that have already ended in the expired tab', function (): void {
    config()->set('app.timezone', 'UTC');
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-02-23', 'UTC'));

    Helpers::setTestSettingsOverride([
        'general' => [
            'currency' => 'INR',
        ],
        'subscriptions' => [
            'expiring_days' => 7,
        ],
    ]);

    $member = Member::factory()->create();
    $plan = Plan::factory()->create();

    $expired = Subscription::factory()->create([
        'member_id' => $member->id,
        'plan_id' => $plan->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-02-20',
    ]);

    Subscription::factory()->create([
        'member_id' => $member->id,
        'plan_id' => $plan->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-02-26',
    ]);

    Subscription::factory()->create([
        'member_id' => $member->id,
        'plan_id' => $plan->id,
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
    ]);

    $widget = new MembershipOverviewSubscriptionsTableWidget;
    $widget->activeTab = 'expired';

    $method = new ReflectionMethod($widget, 'getActiveTabQuery');
    $method->setAccessible(true);

    /** @var Builder $query */
    $query = $method->invoke($widget);

    $ids = $query->pluck('id')->all();

    expect($ids)->toBe([$expired->id]);
});

it('shows only the top 5 expiring memberships on the dashboard widget', function (): void {
    config()->set('app.timezone', 'UTC');
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-02-23', 'UTC'));

    Helpers::setTestSettingsOverride([
        'general' => [
            'currency' => 'INR',
        ],
        'subscriptions' => [
            'expiring_days' => 7,
        ],
    ]);

    $member = Member::factory()->create();
    $plan = Plan::factory()->create();

    $records = collect([
        '2026-02-24',
        '2026-02-25',
        '2026-02-26',
        '2026-02-27',
        '2026-02-28',
        '2026-03-01',
    ])->map(fn (string $endDate): Subscription => Subscription::factory()->create([
        'member_id' => $member->id,
        'plan_id' => $plan->id,
        'start_date' => '2026-01-01',
        'end_date' => $endDate,
    ]));

    $expected = $records->take(5);
    $excluded = $records->last();

    $component = Livewire::test(MembershipOverviewSubscriptionsTableWidget::class)
        ->loadTable()
        ->assertCanSeeTableRecords($expected)
        ->assertCanNotSeeTableRecords([$excluded]);

    expect($component->instance()->getTable()->hasColumnManager())->toBeFalse();
});

it('shows only the top 5 expired memberships on the dashboard widget', function (): void {
    config()->set('app.timezone', 'UTC');
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-02-23', 'UTC'));

    Helpers::setTestSettingsOverride([
        'general' => [
            'currency' => 'INR',
        ],
        'subscriptions' => [
            'expiring_days' => 7,
        ],
    ]);

    $member = Member::factory()->create();
    $plan = Plan::factory()->create();

    $records = collect([
        '2026-02-22',
        '2026-02-21',
        '2026-02-20',
        '2026-02-19',
        '2026-02-18',
        '2026-02-17',
    ])->map(fn (string $endDate): Subscription => Subscription::factory()->create([
        'member_id' => $member->id,
        'plan_id' => $plan->id,
        'start_date' => '2026-01-01',
        'end_date' => $endDate,
    ]));

    $expected = $records->take(5);
    $excluded = $records->last();

    Livewire::test(MembershipOverviewSubscriptionsTableWidget::class, [
        'activeTab' => 'expired',
    ])
        ->loadTable()
        ->assertCanSeeTableRecords($expected)
        ->assertCanNotSeeTableRecords([$excluded]);
});
