<?php

use App\Helpers\Helpers;
use App\Jobs\SendInvoicePaymentReceiptEmail;
use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Helpers::setTestSettingsOverride(null);
});

it('dispatches the payment receipt email job when enabled in settings', function (): void {
    Queue::fake();

    Helpers::setTestSettingsOverride([
        'general' => [
            'gym_name' => 'Demo Gym',
            'gym_email' => 'gym@example.com',
            'currency' => 'INR',
        ],
        'charges' => [
            'taxes' => 0,
            'discounts' => [],
        ],
        'notifications' => [
            'email' => [
                'enabled' => true,
                'auto_send_invoice_issued' => false,
                'auto_send_payment_receipt' => true,
            ],
        ],
    ]);

    $member = Member::factory()->create([
        'email' => 'member@example.com',
    ]);

    $plan = Plan::factory()->create([
        'amount' => 1000,
        'days' => 30,
    ]);

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

    $transaction = InvoiceTransaction::query()->create([
        'invoice_id' => $invoice->id,
        'type' => 'payment',
        'amount' => 250,
        'occurred_at' => now()->timezone(config('app.timezone')),
        'payment_method' => 'cash',
        'note' => 'Manual payment',
        'reference_id' => null,
        'created_by' => null,
    ]);

    Queue::assertPushed(SendInvoicePaymentReceiptEmail::class, function (SendInvoicePaymentReceiptEmail $job) use ($invoice, $transaction, $member): bool {
        return $job->invoiceId === $invoice->id
            && $job->invoiceTransactionId === $transaction->id
            && $job->toEmail === $member->email
            && $job->tries === 3
            && $job->timeout === 60
            && $job->uniqueFor === 3600
            && $job->uniqueId() === "{$transaction->id}:member@example.com"
            && $job->backoff === [10, 60, 300]
            && $job->afterCommit === true;
    });
});
