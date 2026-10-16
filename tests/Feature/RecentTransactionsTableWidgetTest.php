<?php

use App\Filament\Widgets\Analytics\RecentTransactionsTableWidget;
use App\Helpers\Helpers;
use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Helpers::setTestSettingsOverride(null);
});

it('shows only the top 5 recent transactions on the dashboard widget', function (): void {
    config()->set('app.timezone', 'UTC');
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-02-23', 'UTC'));

    Helpers::setTestSettingsOverride([
        'general' => [
            'currency' => 'INR',
        ],
        'charges' => [
            'taxes' => 0,
            'discounts' => [],
        ],
        'subscriptions' => [
            'expiring_days' => 7,
        ],
        'payments' => [
            'provider' => 'stripe',
        ],
    ]);

    $member = Member::factory()->create();
    $plan = Plan::factory()->create();

    $subscription = Subscription::factory()->create([
        'member_id' => $member->id,
        'plan_id' => $plan->id,
        'start_date' => '2026-02-01',
        'end_date' => '2026-03-01',
    ]);

    $invoice = Invoice::factory()->create([
        'subscription_id' => $subscription->id,
        'date' => '2026-02-20',
        'due_date' => '2026-02-27',
        'subscription_fee' => 1000,
        'discount_amount' => 0,
        'paid_amount' => 0,
    ]);

    $transactions = collect([
        '2026-02-17 10:00:00',
        '2026-02-18 10:00:00',
        '2026-02-19 10:00:00',
        '2026-02-20 10:00:00',
        '2026-02-21 10:00:00',
        '2026-02-22 10:00:00',
    ])->map(fn (string $occurredAt): InvoiceTransaction => InvoiceTransaction::query()->create([
        'invoice_id' => $invoice->id,
        'type' => $occurredAt === '2026-02-22 10:00:00' ? 'refund' : 'payment',
        'amount' => 100,
        'occurred_at' => $occurredAt,
        'payment_method' => 'offline',
        'reference_id' => "test:{$occurredAt}",
    ]));

    $expected = $transactions->sortByDesc('occurred_at')->take(5)->values();
    $excluded = $transactions->sortBy('occurred_at')->first();

    $component = Livewire::test(RecentTransactionsTableWidget::class)
        ->loadTable()
        ->assertCanSeeTableRecords($expected)
        ->assertCanNotSeeTableRecords([$excluded])
        ->assertSee(__('app.transactions.payment'))
        ->assertSee(__('app.transactions.refund'));

    expect($component->instance()->getTable()->hasColumnManager())->toBeFalse();
});
