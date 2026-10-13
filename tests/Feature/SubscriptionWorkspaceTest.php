<?php

use App\Enums\Status;
use App\Enums\SubscriptionActivityType;
use App\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Filament\Resources\Subscriptions\Pages\ViewSubscription;
use App\Filament\Resources\Subscriptions\RelationManagers\InvoicesRelationManager;
use App\Helpers\Helpers;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Invoices\InvoiceTransactionService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-28 10:00:00');
    Helpers::setTestSettingsOverride([
        'general' => ['currency' => 'INR'],
        'charges' => ['taxes' => 0, 'discounts' => []],
        'subscriptions' => ['expiring_days' => 7],
        'payments' => ['provider' => 'stripe'],
        'notifications' => ['email' => []],
    ]);

    Role::findOrCreate('super_admin', 'web');
    $this->actingAs(User::factory()->create()->assignRole('super_admin'));
    Filament::setCurrentPanel('admin');
});

afterEach(function (): void {
    Carbon::setTestNow();
    Helpers::setTestSettingsOverride(null);
});

it('shows membership urgency and outstanding billing in the subscription workspace', function (): void {
    $member = Member::factory()->create([
        'code' => 'MEM-100',
        'name' => 'Taylor Member',
    ]);
    $plan = Plan::factory()->create([
        'code' => 'MONTHLY',
        'name' => 'Monthly',
    ]);
    $subscription = Subscription::factory()->for($member)->for($plan)->create([
        'start_date' => '2026-09-01',
        'end_date' => '2026-10-28',
        'status' => Status::Ongoing,
    ]);
    $invoice = Invoice::factory()->for($subscription)->create([
        'number' => 'INV-100',
        'subscription_fee' => 100,
        'discount_amount' => 0,
        'paid_amount' => 40,
        'due_date' => '2026-10-01',
    ])->refresh();

    Livewire::test(ListSubscriptions::class)
        ->assertSee('30 Days Left')
        ->assertSee(Helpers::formatCurrency((float) $invoice->due_amount));

    Livewire::test(ViewSubscription::class, ['record' => $subscription->getRouteKey()])
        ->assertSchemaComponentExists('subscription_sections.details_tab')
        ->assertSchemaComponentExists('subscription_sections.activity_tab')
        ->assertSchemaComponentExists('subscription_sections.invoice_tab')
        ->assertSee('Taylor Member')
        ->assertSee('Monthly')
        ->assertSee('INV-100')
        ->assertSee(Helpers::formatCurrency((float) $invoice->due_amount));

    Livewire::test(InvoicesRelationManager::class, [
        'ownerRecord' => $subscription,
        'pageClass' => ViewSubscription::class,
    ])
        ->assertCanSeeTableRecords([$invoice])
        ->assertTableColumnHidden('id')
        ->assertTableColumnHidden('subscription.member.name')
        ->assertTableColumnVisible('discount_amount');
});

it('does not count the waiting period before an upcoming subscription starts', function (): void {
    $member = Member::factory()->create([
        'name' => 'Future Member',
    ]);
    $plan = Plan::factory()->create();
    Subscription::factory()->for($member)->for($plan)->create([
        'start_date' => '2026-10-05',
        'end_date' => '2026-11-04',
        'status' => Status::Upcoming,
    ]);

    Livewire::test(ListSubscriptions::class)
        ->assertSee('Future Member')
        ->assertDontSee('37 Days Left');
});

it('renews a subscription from the subscriptions table', function (): void {
    $member = Member::factory()->create();
    $plan = Plan::factory()->create([
        'amount' => 199.99,
        'days' => 30,
    ]);
    $subscription = Subscription::factory()->for($member)->for($plan)->create([
        'start_date' => '2026-08-28',
        'end_date' => '2026-09-27',
        'status' => Status::Expired,
    ]);

    Livewire::test(ListSubscriptions::class)
        ->callTableAction('renew', $subscription, [
            'plan_id' => $plan->getKey(),
            'start_date' => '2026-09-28',
            'end_date' => '2026-10-27',
            'invoice_number' => 'INV-RENEW-001',
            'invoice_date' => '2026-09-28',
            'invoice_due_date' => '2026-09-28',
            'payment_method' => 'cash',
            'discount_amount' => 0,
            'paid_amount' => 0,
        ])
        ->assertHasNoTableActionErrors()
        ->assertNotified();

    $renewal = Subscription::query()
        ->where('renewed_from_subscription_id', $subscription->getKey())
        ->sole();
    $invoice = Invoice::query()
        ->where('subscription_id', $renewal->getKey())
        ->sole();

    expect($renewal->start_date?->toDateString())->toBe('2026-09-28')
        ->and($renewal->end_date?->toDateString())->toBe('2026-10-27')
        ->and($subscription->fresh()->status)->toBe(Status::Renewed)
        ->and((float) $invoice->subscription_fee)->toBe(200.0);
});

it('records membership and billing events on the subscription timeline', function (): void {
    $member = Member::factory()->create();
    $plan = Plan::factory()->create(['name' => 'Monthly']);
    $subscription = Subscription::factory()->for($member)->for($plan)->create([
        'start_date' => '2026-09-01',
        'end_date' => '2026-10-28',
        'status' => Status::Ongoing,
    ]);
    $subscription->update(['status' => Status::Expiring]);

    $invoice = Invoice::query()->create([
        'number' => 'INV-ACTIVITY',
        'subscription_id' => $subscription->getKey(),
        'date' => '2026-09-28',
        'subscription_fee' => 100,
        'discount_amount' => 0,
        'paid_amount' => 0,
    ])->refresh();

    $transactions = app(InvoiceTransactionService::class);
    $transactions->record($invoice, [
        'type' => 'payment',
        'amount' => 40,
        'occurred_at' => '2026-09-28 10:00:00',
        'payment_method' => 'cash',
        'note' => 'Front desk payment.',
        'created_by' => auth()->id(),
    ]);
    $transactions->refundPaidBalance($invoice->fresh(), [
        'occurred_at' => '2026-09-28 11:00:00',
        'note' => 'Membership cancelled.',
        'created_by' => auth()->id(),
    ]);

    expect(Activity::query()->forSubject($subscription)->forEvent(SubscriptionActivityType::SubscriptionCreated->value)->count())->toBe(1)
        ->and(Activity::query()->forSubject($subscription)->forEvent(SubscriptionActivityType::SubscriptionStatusChanged->value)->count())->toBe(1)
        ->and(Activity::query()->forSubject($subscription)->forEvent(SubscriptionActivityType::InvoiceCreated->value)->count())->toBe(1)
        ->and(Activity::query()->forSubject($subscription)->forEvent(SubscriptionActivityType::InvoiceStatusChanged->value)->count())->toBe(2)
        ->and(Activity::query()->forSubject($subscription)->forEvent(SubscriptionActivityType::PaymentRecorded->value)->count())->toBe(1)
        ->and(Activity::query()->forSubject($subscription)->forEvent(SubscriptionActivityType::RefundRecorded->value)->count())->toBe(1);

    $subscription->recordActivity(SubscriptionActivityType::InvoiceStatusChanged, [
        'invoice' => 'GY-89090',
        'new' => Status::Issued->value,
    ]);

    Livewire::test(ViewSubscription::class, ['record' => $subscription->getRouteKey()])
        ->assertSee('added a Monthly subscription')
        ->assertSee('created invoice INV-ACTIVITY')
        ->assertSee('recorded a payment')
        ->assertSee('recorded a refund')
        ->assertSee('Front desk payment.')
        ->assertSee('Membership cancelled.')
        ->assertSee('set invoice GY-89090 status to Issued')
        ->assertDontSee('from N/A to Issued');
});
