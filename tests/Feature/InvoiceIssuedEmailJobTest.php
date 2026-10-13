<?php

use App\Enums\CommunicationTemplateKey;
use App\Helpers\Helpers;
use App\Jobs\SendInvoiceIssuedEmail;
use App\Models\CommunicationAutomation;
use App\Models\CommunicationTemplate;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Helpers::setTestSettingsOverride(null);
});

it('uses the delivery record as the uniqueness boundary', function (): void {
    $firstDelivery = new SendInvoiceIssuedEmail(
        invoiceId: 10,
        toEmail: 'member@example.com',
        deliveryId: 101,
    );
    $secondDelivery = new SendInvoiceIssuedEmail(
        invoiceId: 10,
        toEmail: 'member@example.com',
        deliveryId: 102,
    );

    expect($firstDelivery->uniqueId())->toBe('delivery:101')
        ->and($secondDelivery->uniqueId())->toBe('delivery:102');
});

it('dispatches the invoice issued email job when enabled in settings', function (): void {
    Queue::fake();
    config([
        'mail.default' => 'smtp',
        'mail.from.address' => 'notifications@gymie.test',
    ]);

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
            ],
        ],
    ]);

    CommunicationAutomation::query()->create([
        'event' => CommunicationTemplateKey::InvoiceIssued,
        'communication_template_id' => CommunicationTemplate::query()
            ->where('key', CommunicationTemplateKey::InvoiceIssued->value)
            ->value('id'),
        'is_enabled' => true,
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

    Queue::assertPushed(SendInvoiceIssuedEmail::class, function (SendInvoiceIssuedEmail $job) use ($invoice, $member): bool {
        return $job->invoiceId === $invoice->id
            && $job->toEmail === $member->email
            && $job->tries === 3
            && $job->timeout === 60
            && $job->failOnTimeout
            && $job->uniqueFor === 3600
            && $job->deliveryId !== null
            && $job->uniqueId() === "delivery:{$job->deliveryId}"
            && $job->backoff === [10, 60, 300]
            && $job->afterCommit === true;
    });
});
