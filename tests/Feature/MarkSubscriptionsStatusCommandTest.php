<?php

namespace Tests\Feature;

use App\Enums\SubscriptionActivityType;
use App\Helpers\Helpers;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MarkSubscriptionsStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00'));

        Helpers::setTestSettingsOverride([
            'subscriptions' => [
                'expiring_days' => 7,
            ],
        ]);

        // Ensure the super_admin role exists for the test user
        Role::create(['name' => 'super_admin']);
    }

    protected function tearDown(): void
    {
        Helpers::setTestSettingsOverride(null);
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_marks_expired_and_expiring_subscriptions_and_sends_database_notification(): void
    {
        $admin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'test@example.com',
        ]);

        $admin->assignRole('super_admin');

        $expired = Subscription::factory()->create([
            'start_date' => now()->subMonth(),
            'end_date' => now()->subDay(),
            'status' => 'ongoing',
        ]);
        $expiring = Subscription::factory()->create([
            'start_date' => now()->subMonth(),
            'end_date' => now()->addDays(3),
            'status' => 'ongoing',
        ]);
        Subscription::factory()->create([
            'start_date' => now()->subMonth(),
            'end_date' => now()->addDays(14),
            'status' => 'ongoing',
        ]);

        $this->artisan('gymie:subscriptions', [
            '--mark-expired' => true,
            '--mark-expiring' => true,
        ])
            ->assertExitCode(0);

        $this->assertSame(1, Subscription::query()->where('status', 'expired')->count());
        $this->assertSame(1, Subscription::query()->where('status', 'expiring')->count());
        $this->assertSame(1, Subscription::query()->where('status', 'ongoing')->count());
        $this->assertSame(1, Activity::query()
            ->forSubject($expired)
            ->forEvent(SubscriptionActivityType::SubscriptionStatusChanged->value)
            ->count());
        $this->assertSame(1, Activity::query()
            ->forSubject($expiring)
            ->forEvent(SubscriptionActivityType::SubscriptionStatusChanged->value)
            ->count());

        $notification = $admin->notifications()->latest()->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString(__('app.notifications.subscription_status_update_title'), (string) ($notification->data['title'] ?? ''));
        $this->assertStringContainsString('1 expired', (string) ($notification->data['body'] ?? ''));
        $this->assertStringContainsString('1 expiring (≤ 7 days)', (string) ($notification->data['body'] ?? ''));
    }
}
