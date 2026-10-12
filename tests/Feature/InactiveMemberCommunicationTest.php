<?php

use App\Enums\CommunicationTemplateKey;
use App\Helpers\Helpers;
use App\Jobs\SendCommunicationEmail;
use App\Models\Attendance;
use App\Models\CommunicationAutomation;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationTemplate;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-09 09:00:00');
    config([
        'mail.default' => 'smtp',
        'mail.from.address' => 'notifications@gymie.test',
    ]);
    Helpers::setTestSettingsOverride([
        'general' => ['gym_name' => 'Demo Gym'],
        'notifications' => ['email' => ['enabled' => true]],
    ]);

    $event = CommunicationTemplateKey::MemberInactive;
    CommunicationAutomation::query()->create([
        'event' => $event,
        'communication_template_id' => CommunicationTemplate::query()->where('key', $event->value)->value('id'),
        'is_enabled' => true,
    ]);
});

afterEach(function (): void {
    Helpers::setTestSettingsOverride(null);
});

it('queues one reminder at each supported inactivity milestone and ignores ineligible members', function (): void {
    Queue::fake([SendCommunicationEmail::class]);
    $plan = Plan::factory()->create();
    $sevenDayMember = Member::factory()->create(['email' => 'seven@example.com', 'status' => 'active']);
    Subscription::factory()->for($sevenDayMember)->for($plan)->current()->create();
    Attendance::factory()->for($sevenDayMember)->create(['checked_in_at' => now()->subDays(7)->setTime(8, 0)]);

    $fourteenDayMember = Member::factory()->create(['email' => 'fourteen@example.com', 'status' => 'active']);
    Subscription::factory()->for($fourteenDayMember)->for($plan)->create([
        'start_date' => now()->subDays(14)->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'ongoing',
    ]);

    $thirtyDayMember = Member::factory()->create(['email' => 'thirty@example.com', 'status' => 'active']);
    Subscription::factory()->for($thirtyDayMember)->for($plan)->current()->create();
    Attendance::factory()->for($thirtyDayMember)->create(['checked_in_at' => now()->subDays(30)->setTime(8, 0)]);

    $tenDayMember = Member::factory()->create(['email' => 'ten@example.com', 'status' => 'active']);
    Subscription::factory()->for($tenDayMember)->for($plan)->current()->create();
    Attendance::factory()->for($tenDayMember)->create(['checked_in_at' => now()->subDays(10)->setTime(8, 0)]);

    $expiredMember = Member::factory()->create(['email' => 'expired@example.com', 'status' => 'active']);
    Subscription::factory()->for($expiredMember)->for($plan)->create([
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->subDay()->toDateString(),
        'status' => 'expired',
    ]);

    $this->artisan('gymie:inactive-members')
        ->assertSuccessful();
    $this->artisan('gymie:inactive-members')
        ->assertSuccessful();

    $deliveries = CommunicationDelivery::query()->get();

    expect($deliveries)->toHaveCount(3)
        ->and($deliveries->pluck('related_id')->sort()->values()->all())->toBe([
            $sevenDayMember->getKey(),
            $fourteenDayMember->getKey(),
            $thirtyDayMember->getKey(),
        ])
        ->and($deliveries->pluck('retry_payload')->pluck('variables.inactive_days')->sort()->values()->all())->toBe([7, 14, 30]);

    Queue::assertPushed(SendCommunicationEmail::class, 3);
});
