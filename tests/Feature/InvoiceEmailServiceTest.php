<?php

use App\Helpers\Helpers;
use App\Mail\InvoiceIssuedMail;
use App\Mail\TestEmail;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Email\InvoiceEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Helpers::setTestSettingsOverride(null);
});

it('sends invoice issued email with reply-to when gym email is configured', function (): void {
    config(['mail.from.address' => 'notifications@gymie.test']);

    Helpers::setTestSettingsOverride([
        'general' => [
            'gym_name' => 'Demo Gym',
            'gym_email' => 'gym@example.com',
            'gym_contact' => '9999999999',
            'currency' => 'INR',
        ],
        'charges' => [
            'taxes' => 0,
            'discounts' => [],
        ],
    ]);

    Mail::fake();

    $member = Member::factory()->create([
        'name' => 'Test Member',
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

    app(InvoiceEmailService::class)->sendInvoiceIssuedEmail(
        invoiceId: $invoice->id,
        toEmail: $member->email,
        note: 'Hello',
    );

    Mail::assertSent(InvoiceIssuedMail::class, function (InvoiceIssuedMail $mail) use ($invoice): bool {
        return $mail->invoice->is($invoice)
            && $mail->hasFrom('notifications@gymie.test', 'Demo Gym')
            && $mail->hasReplyTo('gym@example.com')
            && count($mail->attachments()) === 1;
    });
});

it('sends a synchronous test email with the configured gym identity', function (): void {
    config([
        'mail.default' => 'smtp',
        'mail.from.address' => 'notifications@gymie.test',
    ]);

    Helpers::setTestSettingsOverride([
        'general' => [
            'gym_name' => 'Demo Gym',
            'gym_email' => 'gym@example.com',
            'gym_contact' => '9999999999',
        ],
    ]);

    Mail::fake();

    app(InvoiceEmailService::class)->sendTestEmail('owner@example.com');

    Mail::assertSent(TestEmail::class, function (TestEmail $mail): bool {
        return $mail->hasTo('owner@example.com')
            && $mail->hasFrom('notifications@gymie.test', 'Demo Gym')
            && $mail->hasReplyTo('gym@example.com', 'Demo Gym');
    });
});

it('recognizes local-only mail transports as not configured for delivery', function (): void {
    config([
        'mail.default' => 'log',
        'mail.from.address' => 'notifications@gymie.test',
    ]);

    expect(app(InvoiceEmailService::class)->isMailDeliveryConfigured())->toBeFalse();
});

it('escapes the gym identity rendered in the test email', function (): void {
    $gymName = '<script>alert("xss")</script>';
    $mail = new TestEmail(
        fromAddress: 'notifications@gymie.test',
        gymName: $gymName,
        gymEmail: 'gym@example.com',
        gymContact: '9999999999',
    );

    $mail
        ->assertSeeInHtml($gymName)
        ->assertDontSeeInHtml($gymName, escape: false);
});
