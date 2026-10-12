<?php

use App\Enums\CommunicationTemplateKey;
use App\Helpers\Helpers;
use App\Jobs\SendInvoicePaymentReminderEmail;
use App\Models\CommunicationAutomation;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationTemplate;
use App\Models\Invoice;
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
        'charges' => ['taxes' => 0, 'discounts' => []],
        'notifications' => ['email' => ['enabled' => true]],
    ]);

    $event = CommunicationTemplateKey::InvoiceOverdue;
    CommunicationAutomation::query()->create([
        'event' => $event,
        'communication_template_id' => CommunicationTemplate::query()->where('key', $event->value)->value('id'),
        'is_enabled' => true,
    ]);
});

afterEach(function (): void {
    Helpers::setTestSettingsOverride(null);
});

it('marks an invoice overdue and queues one automatic reminder', function (): void {
    Queue::fake([SendInvoicePaymentReminderEmail::class]);
    $member = Member::factory()->create(['email' => 'member@example.com']);
    $subscription = Subscription::factory()
        ->for($member)
        ->for(Plan::factory())
        ->current()
        ->create();
    $invoice = Invoice::factory()->for($subscription)->create([
        'date' => now()->toDateString(),
        'due_date' => now()->toDateString(),
        'subscription_fee' => 1000,
        'discount_amount' => 0,
        'paid_amount' => 0,
        'status' => 'issued',
    ])->refresh();
    $this->travelTo(now()->addDay());

    $this->artisan('gymie:invoices', ['--mark-overdue' => true])
        ->expectsOutput('1 invoice(s) marked as overdue.')
        ->assertSuccessful();
    $this->artisan('gymie:invoices', ['--mark-overdue' => true])
        ->expectsOutput('0 invoice(s) marked as overdue.')
        ->assertSuccessful();

    $delivery = CommunicationDelivery::query()->sole();

    expect($invoice->refresh()->status->value)->toBe('overdue')
        ->and($delivery->template->key)->toBe(CommunicationTemplateKey::InvoiceOverdue->value)
        ->and($delivery->related->is($invoice))->toBeTrue();

    Queue::assertPushed(SendInvoicePaymentReminderEmail::class, 1);
});

it('queues the same reminder when ledger synchronization makes an invoice overdue', function (): void {
    Queue::fake([SendInvoicePaymentReminderEmail::class]);
    $member = Member::factory()->create(['email' => 'member@example.com']);
    $subscription = Subscription::factory()
        ->for($member)
        ->for(Plan::factory())
        ->current()
        ->create();

    $invoice = Invoice::factory()->for($subscription)->create([
        'date' => now()->subDays(2)->toDateString(),
        'due_date' => now()->subDay()->toDateString(),
        'subscription_fee' => 1000,
        'discount_amount' => 0,
        'paid_amount' => 0,
        'status' => 'issued',
    ])->refresh();

    expect($invoice->status->value)->toBe('overdue')
        ->and(CommunicationDelivery::query()->sole()->template->key)
        ->toBe(CommunicationTemplateKey::InvoiceOverdue->value);

    Queue::assertPushed(SendInvoicePaymentReminderEmail::class, 1);
});
