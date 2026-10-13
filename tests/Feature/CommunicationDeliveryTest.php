<?php

use App\Enums\CommunicationDeliveryStatus;
use App\Enums\CommunicationRetryHandler;
use App\Enums\CommunicationTemplateKey;
use App\Helpers\Helpers;
use App\Jobs\SendCommunicationEmail;
use App\Jobs\SendInvoiceIssuedEmail;
use App\Jobs\SendInvoicePaymentReceiptEmail;
use App\Mail\CommunicationMail;
use App\Mail\InvoiceIssuedMail;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationTemplate;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Communication\CommunicationService;
use App\Services\Communication\RetryCommunicationDelivery;
use App\Services\Email\InvoiceEmailSender;
use App\Services\Email\InvoiceEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'mail.default' => 'smtp',
        'mail.from.address' => 'notifications@gymie.test',
    ]);

    Helpers::setTestSettingsOverride([
        'general' => [
            'gym_name' => 'Demo Gym',
            'gym_email' => 'gym@example.com',
            'gym_contact' => '9999999999',
            'currency' => 'INR',
        ],
        'charges' => ['taxes' => 0, 'discounts' => []],
        'notifications' => [
            'email' => [
                'enabled' => false,
                'auto_send_invoice_issued' => false,
                'auto_send_payment_receipt' => false,
            ],
        ],
    ]);
});

it('records a skipped delivery instead of dispatching when outbound email is unavailable', function (): void {
    Queue::fake([SendInvoiceIssuedEmail::class]);
    config(['mail.default' => 'log']);
    [$invoice, $member] = invoiceForCommunication();

    app(InvoiceEmailService::class)->queueInvoiceIssuedEmail(
        invoiceId: $invoice->getKey(),
        toEmail: $member->email,
    );

    $delivery = CommunicationDelivery::query()->sole();

    expect($delivery->status)->toBe(CommunicationDeliveryStatus::Skipped)
        ->and($delivery->failure_reason)->toBe(__('app.notifications.mail_not_configured'));

    Queue::assertNotPushed(SendInvoiceIssuedEmail::class);
});

afterEach(function (): void {
    Helpers::setTestSettingsOverride(null);
});

it('records a queued delivery before dispatching an email job', function (): void {
    Queue::fake([SendInvoiceIssuedEmail::class]);
    $actor = User::factory()->create();
    [$invoice, $member] = invoiceForCommunication();

    app(InvoiceEmailService::class)->queueInvoiceIssuedEmail(
        invoiceId: $invoice->getKey(),
        toEmail: $member->email,
        actorId: $actor->getKey(),
    );

    $delivery = CommunicationDelivery::query()->sole();

    expect($delivery->status)->toBe(CommunicationDeliveryStatus::Queued)
        ->and($delivery->recipient)->toBe($member->email)
        ->and($delivery->actor->is($actor))->toBeTrue()
        ->and($delivery->related->is($invoice))->toBeTrue()
        ->and($delivery->template->key)->toBe(CommunicationTemplateKey::InvoiceIssued->value)
        ->and($delivery->retry_payload)->toMatchArray([
            'handler' => CommunicationRetryHandler::InvoiceIssued->value,
            'invoice_id' => $invoice->getKey(),
        ]);

    Queue::assertPushed(SendInvoiceIssuedEmail::class, fn (SendInvoiceIssuedEmail $job): bool => $job->deliveryId === $delivery->getKey());
});

it('does not dispatch invoice email when its member has opted out', function (): void {
    Queue::fake([SendInvoiceIssuedEmail::class]);
    [$invoice, $member] = invoiceForCommunication();
    $member->update(['email_communication_enabled' => false]);

    app(InvoiceEmailService::class)->queueInvoiceIssuedEmail(
        invoiceId: $invoice->getKey(),
        toEmail: $member->email,
    );

    $delivery = CommunicationDelivery::query()->sole();

    expect($delivery->status)->toBe(CommunicationDeliveryStatus::Skipped)
        ->and($delivery->failure_reason)->toBe(__('app.communication.member_email_opted_out'));

    Queue::assertNotPushed(SendInvoiceIssuedEmail::class);
});

it('encrypts retry context and requeues a failed template delivery', function (): void {
    Queue::fake([SendCommunicationEmail::class]);
    $template = CommunicationTemplate::query()
        ->where('key', CommunicationTemplateKey::MemberWelcome->value)
        ->sole();
    $delivery = CommunicationDelivery::factory()->create([
        'communication_template_id' => $template->getKey(),
        'status' => CommunicationDeliveryStatus::Failed,
        'failed_at' => now(),
        'failure_reason' => 'Temporary provider failure.',
        'retry_payload' => [
            'handler' => CommunicationRetryHandler::Communication->value,
            'variables' => [
                'member_name' => 'Taylor Member',
                'member_code' => 'MEM-001',
            ],
        ],
    ]);

    $storedPayload = DB::table('communication_deliveries')
        ->where('id', $delivery->getKey())
        ->value('retry_payload');

    expect($storedPayload)->toBeString()
        ->not->toContain('Taylor Member')
        ->and($delivery->toArray())->not->toHaveKey('retry_payload');

    app(RetryCommunicationDelivery::class)->handle($delivery);

    expect($delivery->refresh())
        ->status->toBe(CommunicationDeliveryStatus::Queued)
        ->subject->toBeNull()
        ->failed_at->toBeNull()
        ->failure_reason->toBeNull();

    Queue::assertPushed(SendCommunicationEmail::class, fn (SendCommunicationEmail $job): bool => $job->deliveryId === $delivery->getKey()
        && $job->tries === 3
        && $job->timeout === 60
        && $job->failOnTimeout
        && $job->backoff === [10, 60, 300]);
});

it('requeues payment receipts with their transaction context', function (): void {
    Queue::fake([SendInvoicePaymentReceiptEmail::class]);
    $delivery = CommunicationDelivery::factory()->create([
        'status' => CommunicationDeliveryStatus::Failed,
        'failed_at' => now(),
        'retry_payload' => [
            'handler' => CommunicationRetryHandler::PaymentReceipt->value,
            'invoice_id' => 42,
            'invoice_transaction_id' => 84,
            'note' => 'Thank you.',
        ],
    ]);

    app(RetryCommunicationDelivery::class)->handle($delivery);

    Queue::assertPushed(SendInvoicePaymentReceiptEmail::class, function (SendInvoicePaymentReceiptEmail $job) use ($delivery): bool {
        return $job->invoiceId === 42
            && $job->invoiceTransactionId === 84
            && $job->note === 'Thank you.'
            && $job->deliveryId === $delivery->getKey();
    });
});

it('rejects retry when context is missing or mail delivery is unavailable', function (): void {
    Queue::fake();
    $deliveryWithoutContext = CommunicationDelivery::factory()->create([
        'status' => CommunicationDeliveryStatus::Failed,
        'failed_at' => now(),
        'retry_payload' => null,
    ]);

    expect(fn () => app(RetryCommunicationDelivery::class)->handle($deliveryWithoutContext))
        ->toThrow(ValidationException::class, __('app.communication.validation.delivery_not_retryable'));

    $delivery = CommunicationDelivery::factory()->create([
        'status' => CommunicationDeliveryStatus::Failed,
        'failed_at' => now(),
        'retry_payload' => [
            'handler' => CommunicationRetryHandler::Communication->value,
            'variables' => [],
        ],
    ]);
    config(['mail.default' => 'log']);

    expect(fn () => app(RetryCommunicationDelivery::class)->handle($delivery))
        ->toThrow(ValidationException::class, __('app.notifications.mail_not_configured'));

    expect($delivery->refresh()->status)->toBe(CommunicationDeliveryStatus::Failed);
    Queue::assertNothingPushed();
});

it('uses edited templates and records successful delivery without storing the message body', function (): void {
    config(['mail.from.address' => 'notifications@gymie.test']);
    Mail::fake();
    [$invoice, $member] = invoiceForCommunication();

    CommunicationTemplate::query()
        ->where('key', CommunicationTemplateKey::InvoiceIssued->value)
        ->sole()
        ->update([
            'subject' => 'Invoice {{ invoice_number }} for {{ member_name }}',
            'body' => 'Hello {{ member_name }}, balance: {{ due }}.',
        ]);

    app(InvoiceEmailSender::class)->sendInvoiceIssuedEmail(
        invoiceId: $invoice->getKey(),
        toEmail: $member->email,
    );

    $delivery = CommunicationDelivery::query()->sole();

    expect($delivery->status)->toBe(CommunicationDeliveryStatus::Sent)
        ->and($delivery->subject)->toBe("Invoice {$invoice->number} for {$member->name}")
        ->and($delivery->sent_at)->not->toBeNull()
        ->and($delivery->getAttributes())->not->toHaveKey('body');

    Mail::assertSent(InvoiceIssuedMail::class, fn (InvoiceIssuedMail $mail): bool => str_contains($mail->messageBody, "Hello {$member->name}, balance:"));
});

it('prefixes test deliveries so they cannot be mistaken for live messages', function (): void {
    Mail::fake();
    $template = CommunicationTemplate::query()
        ->where('key', CommunicationTemplateKey::MemberWelcome->value)
        ->sole();
    $delivery = CommunicationDelivery::factory()->create([
        'communication_template_id' => $template->getKey(),
        'is_test' => true,
        'subject' => null,
        'status' => CommunicationDeliveryStatus::Queued,
    ]);

    app(CommunicationService::class)->send(
        deliveryId: (int) $delivery->getKey(),
        variables: $template->eventKey()?->sampleVariables() ?? [],
    );

    expect($delivery->refresh())
        ->status->toBe(CommunicationDeliveryStatus::Sent)
        ->subject->toBe('[Test] Welcome to Demo Gym');

    Mail::assertSent(CommunicationMail::class, fn (CommunicationMail $mail): bool => $mail->subjectLine === '[Test] Welcome to Demo Gym');
});

it('records a skipped reminder when no balance remains', function (): void {
    Mail::fake();
    [$invoice, $member] = invoiceForCommunication();
    Invoice::query()->whereKey($invoice->getKey())->update([
        'status' => 'paid',
        'paid_amount' => 1000,
        'due_amount' => 0,
    ]);

    app(InvoiceEmailSender::class)->sendPaymentReminderEmail(
        invoiceId: $invoice->getKey(),
        toEmail: $member->email,
    );

    $delivery = CommunicationDelivery::query()->sole();

    expect($delivery->status)->toBe(CommunicationDeliveryStatus::Skipped)
        ->and($delivery->failure_reason)->toBe('Invoice no longer has an outstanding balance.');

    Mail::assertNothingSent();
});

/** @return array{Invoice, Member} */
function invoiceForCommunication(): array
{
    $member = Member::factory()->create([
        'name' => 'Taylor Member',
        'email' => 'taylor@example.com',
    ]);
    $plan = Plan::factory()->create(['amount' => 1000, 'days' => 30]);
    $subscription = Subscription::factory()->for($member)->for($plan)->current()->create();
    $invoice = Invoice::factory()->for($subscription)->create([
        'number' => 'INV-COM-001',
        'date' => '2026-10-07',
        'due_date' => '2026-10-14',
        'subscription_fee' => 1000,
        'discount_amount' => 0,
        'paid_amount' => 0,
    ])->refresh();

    return [$invoice, $member];
}
