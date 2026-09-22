<?php

use App\Helpers\Helpers;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Analytics\AnalyticsService;
use App\Support\Analytics\AnalyticsDateRange;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Helpers::setTestSettingsOverride([
        'general' => [
            'currency' => 'INR',
        ],
        'charges' => [
            'taxes' => 0,
            'discounts' => [],
        ],
        'expenses' => [
            'categories' => ['Rent', 'Equipment'],
        ],
        'subscriptions' => [
            'expiring_days' => 7,
        ],
        'payments' => [
            'provider' => 'stripe',
        ],
    ]);
});

afterEach(function (): void {
    Helpers::setTestSettingsOverride(null);
});

it('calculates collected financial and membership metrics within a custom date range', function (): void {
    $range = AnalyticsDateRange::fromFilters([
        'period' => 'custom',
        'startDate' => '2026-02-01',
        'endDate' => '2026-02-28',
    ]);

    $planA = Plan::factory()->create(['name' => 'Plan A']);
    $planB = Plan::factory()->create(['name' => 'Plan B']);

    $memberActive = Member::factory()->create(['created_at' => '2026-02-05 10:00:00']);
    $memberExpired = Member::factory()->create(['created_at' => '2026-01-01 10:00:00']);

    $activeSubscription = Subscription::factory()->create([
        'member_id' => $memberActive->id,
        'plan_id' => $planA->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-03-01',
    ]);

    $renewalSubscription = Subscription::factory()->create([
        'renewed_from_subscription_id' => $activeSubscription->id,
        'member_id' => $memberActive->id,
        'plan_id' => $planA->id,
        'start_date' => '2026-02-15',
        'end_date' => '2026-03-15',
    ]);

    Subscription::factory()->create([
        'member_id' => $memberExpired->id,
        'plan_id' => $planB->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-02-10',
    ]);

    $invoicePartial = Invoice::factory()->create([
        'subscription_id' => $activeSubscription->id,
        'date' => '2026-02-10',
        'due_date' => '2026-02-20',
        'discount_amount' => 100,
        'subscription_fee' => 1000,
        'paid_amount' => 0,
    ]);

    InvoiceTransaction::query()->create([
        'invoice_id' => $invoicePartial->id,
        'type' => 'payment',
        'amount' => 600,
        'occurred_at' => '2026-02-12 11:00:00',
        'payment_method' => 'offline',
        'reference_id' => 'test:payment-1',
    ]);

    InvoiceTransaction::query()->create([
        'invoice_id' => $invoicePartial->id,
        'type' => 'refund',
        'amount' => 100,
        'occurred_at' => '2026-02-13 11:00:00',
        'payment_method' => 'offline',
        'reference_id' => 'test:refund-1',
    ]);

    $invoiceOutstanding = Invoice::factory()->create([
        'subscription_id' => $renewalSubscription->id,
        'date' => '2026-02-25',
        'due_date' => '2026-02-27',
        'discount_amount' => 0,
        'subscription_fee' => 200,
        'paid_amount' => 0,
    ]);

    $invoicePartial->refresh();
    $invoiceOutstanding->refresh();

    Expense::factory()->create([
        'amount' => 50,
        'date' => '2026-02-14',
        'category' => 'rent',
    ]);

    $service = app(AnalyticsService::class);

    $financial = $service->financialMetrics($range);
    expect($financial)
        ->net_revenue->toBe(1000.0)
        ->collected->toBe(500.0)
        ->refunds->toBe(100.0)
        ->discounts->toBe(100.0)
        ->expenses->toBe(50.0)
        ->profit->toBe(450.0);

    $expectedOutstanding = (float) $invoicePartial->due_amount + (float) $invoiceOutstanding->due_amount;
    expect($financial['outstanding'])->toBe($expectedOutstanding);

    $membership = $service->membershipMetrics($range);
    expect($membership)
        ->active_members->toBe(1)
        ->new_signups->toBe(1)
        ->renewals->toBe(1)
        ->expired_not_renewed->toBe(1);

    $topPlans = $service->topPlansByCollected($range, 2);
    expect($topPlans)->toHaveCount(2);
    expect($topPlans->first()['plan_name'])->toBe('Plan A');
    expect($topPlans->first()['collected'])->toBe(500.0);
});

it('includes every remaining expense category in the other chart segment', function (): void {
    $range = AnalyticsDateRange::fromFilters([
        'period' => 'custom',
        'startDate' => '2026-02-01',
        'endDate' => '2026-02-28',
    ]);

    Expense::factory()
        ->count(55)
        ->sequence(fn (Sequence $sequence): array => [
            'category' => sprintf('Category %02d', $sequence->index + 1),
            'amount' => $sequence->index + 1,
            'date' => '2026-02-14',
        ])
        ->create();

    $breakdown = app(AnalyticsService::class)
        ->expenseCategoryBreakdownForChart($range, 5);

    expect($breakdown)->toHaveCount(6)
        ->and($breakdown->last())->toBe([
            'category' => 'Other',
            'total' => 1275.0,
        ]);
});
