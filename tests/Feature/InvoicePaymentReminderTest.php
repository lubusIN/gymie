<?php

use App\Enums\Status;
use App\Enums\SubscriptionActivityType;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Subscriptions\Pages\ViewSubscription;
use App\Helpers\Helpers;
use App\Jobs\SendInvoicePaymentReminderEmail;
use App\Mail\InvoiceIssuedMail;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Email\InvoiceEmailSender;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-30 10:00:00'));

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
        'charges' => [
            'taxes' => 0,
            'discounts' => [],
        ],
        'notifications' => [
            'email' => [
                'enabled' => false,
                'auto_send_invoice_issued' => false,
                'auto_send_payment_receipt' => false,
            ],
        ],
    ]);

    Role::findOrCreate('super_admin', 'web');
    $this->user = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($this->user);
    Filament::setCurrentPanel('admin');
});

it('disables payment reminders when outbound email is unavailable', function (): void {
    config(['mail.default' => 'log']);
    $member = Member::factory()->create(['email' => 'taylor@example.com']);
    $plan = Plan::factory()->create();
    $subscription = Subscription::factory()->for($member)->for($plan)->current()->create();
    $invoice = Invoice::factory()->for($subscription)->create([
        'date' => now()->toDateString(),
        'due_date' => '2026-10-07',
        'subscription_fee' => 100,
        'discount_amount' => 0,
        'paid_amount' => 0,
    ])->refresh();

    Livewire::test(ListInvoices::class)
        ->assertTableActionVisible('send_payment_reminder', $invoice)
        ->assertTableActionDisabled('send_payment_reminder', $invoice);
});

afterEach(function (): void {
    $this->travelBack();
    Helpers::setTestSettingsOverride(null);
});

it('queues an outstanding payment reminder and records it on the subscription timeline', function (): void {
    Queue::fake([SendInvoicePaymentReminderEmail::class]);

    $member = Member::factory()->create([
        'name' => 'Taylor Member',
        'email' => 'taylor@example.com',
    ]);
    $plan = Plan::factory()->create(['name' => 'Monthly']);
    $subscription = Subscription::factory()->for($member)->for($plan)->current()->create();
    $invoice = Invoice::factory()->for($subscription)->create([
        'number' => 'INV-REM-001',
        'date' => '2026-09-30',
        'due_date' => '2026-10-07',
        'subscription_fee' => 1000,
        'discount_amount' => 0,
        'paid_amount' => 0,
    ])->refresh();

    Livewire::test(ListInvoices::class)
        ->assertTableActionVisible('send_payment_reminder', $invoice)
        ->assertTableActionEnabled('send_payment_reminder', $invoice)
        ->mountTableAction('send_payment_reminder', $invoice)
        ->assertSchemaComponentExists(
            'payment_reminder_member',
            checkComponentUsing: fn ($component): bool => $component instanceof TextEntry,
        )
        ->assertSchemaComponentExists('payment_reminder_invoice')
        ->assertSchemaComponentExists('payment_reminder_due')
        ->assertSchemaComponentExists('payment_reminder_due_date')
        ->assertSchemaComponentExists('payment_reminder_recipient')
        ->assertSchemaComponentExists('note')
        ->setTableActionData([
            'note' => 'Please pay before your next visit.',
        ])
        ->callMountedTableAction()
        ->assertNotified();

    Queue::assertPushed(SendInvoicePaymentReminderEmail::class, function (SendInvoicePaymentReminderEmail $job) use ($invoice): bool {
        return $job->invoiceId === $invoice->getKey()
            && $job->toEmail === 'taylor@example.com'
            && $job->note === 'Please pay before your next visit.'
            && $job->actorId === $this->user->getKey()
            && $job->tries === 3
            && $job->timeout === 60
            && $job->failOnTimeout
            && $job->uniqueFor === 3600
            && $job->deliveryId !== null
            && $job->uniqueId() === "delivery:{$job->deliveryId}"
            && $job->backoff === [10, 60, 300]
            && $job->afterCommit === true;
    });

    $activity = Activity::query()
        ->forSubject($subscription)
        ->forEvent(SubscriptionActivityType::PaymentReminderQueued->value)
        ->sole();

    expect($activity->properties->get('invoice'))->toBe('INV-REM-001')
        ->and((float) $activity->properties->get('amount'))->toBe(1000.0)
        ->and($activity->properties->get('due_date'))->toBe('2026-10-07')
        ->and($activity->properties->get('note'))->toBe('Please pay before your next visit.');

    Livewire::test(ViewSubscription::class, ['record' => $subscription->getRouteKey()])
        ->assertSee('queued a payment reminder for invoice INV-REM-001')
        ->assertSee('Please pay before your next visit.');
});

it('disables reminders when the member has no email address', function (): void {
    $plan = Plan::factory()->create();
    $memberWithoutEmail = Member::factory()->create(['email' => null]);
    $subscriptionWithoutEmail = Subscription::factory()->for($memberWithoutEmail)->for($plan)->current()->create();
    $invoiceWithoutEmail = Invoice::factory()->for($subscriptionWithoutEmail)->create([
        'date' => now()->toDateString(),
        'due_date' => '2026-10-07',
        'subscription_fee' => 100,
        'discount_amount' => 0,
        'paid_amount' => 0,
    ])->refresh();

    Livewire::test(ListInvoices::class)
        ->assertTableActionVisible('send_payment_reminder', $invoiceWithoutEmail)
        ->assertTableActionDisabled('send_payment_reminder', $invoiceWithoutEmail);
});

it('does not expose the reminder action without invoice update permission', function (): void {
    $member = Member::factory()->create();
    $plan = Plan::factory()->create();
    $subscription = Subscription::factory()->for($member)->for($plan)->current()->create();
    $invoice = Invoice::factory()->for($subscription)->create([
        'date' => now()->toDateString(),
        'due_date' => '2026-10-07',
        'subscription_fee' => 100,
        'discount_amount' => 0,
        'paid_amount' => 0,
    ])->refresh();

    $permissions = collect(['ViewAny:Invoice', 'View:Invoice'])
        ->map(fn (string $permission): Permission => Permission::findOrCreate($permission, 'web'));
    $viewer = User::factory()->create();
    $viewer->givePermissionTo($permissions);
    $this->actingAs($viewer);

    Livewire::test(ListInvoices::class)
        ->assertTableActionHidden('send_payment_reminder', $invoice);
});

it('sends the reminder using the current invoice balance and default message', function (): void {
    config(['mail.from.address' => 'notifications@gymie.test']);
    Mail::fake();

    $member = Member::factory()->create([
        'name' => 'Taylor Member',
        'email' => 'taylor@example.com',
    ]);
    $plan = Plan::factory()->create();
    $subscription = Subscription::factory()->for($member)->for($plan)->current()->create();
    $invoice = Invoice::factory()->for($subscription)->create([
        'number' => 'INV-REM-002',
        'due_date' => '2026-10-07',
        'subscription_fee' => 100,
        'discount_amount' => 0,
        'paid_amount' => 0,
    ])->refresh();

    app(InvoiceEmailSender::class)->sendPaymentReminderEmail(
        invoiceId: $invoice->getKey(),
        toEmail: $member->email,
    );

    Mail::assertSent(InvoiceIssuedMail::class, function (InvoiceIssuedMail $mail) use ($invoice): bool {
        return $mail->invoice->is($invoice)
            && $mail->subjectLine === 'Payment reminder - Invoice INV-REM-002'
            && $mail->note === __('app.emails.payment_reminder_note')
            && $mail->hasTo('taylor@example.com')
            && count($mail->attachments()) === 1;
    });
});

it('skips a queued reminder when the invoice has already been paid', function (): void {
    Mail::fake();

    $member = Member::factory()->create(['email' => 'taylor@example.com']);
    $plan = Plan::factory()->create();
    $subscription = Subscription::factory()->for($member)->for($plan)->current()->create();
    $invoice = Invoice::factory()->for($subscription)->create([
        'due_date' => '2026-10-07',
        'subscription_fee' => 100,
        'discount_amount' => 0,
        'paid_amount' => 100,
    ])->refresh();

    expect($invoice->status)->toBe(Status::Paid)
        ->and((float) $invoice->due_amount)->toBe(0.0);

    app(InvoiceEmailSender::class)->sendPaymentReminderEmail(
        invoiceId: $invoice->getKey(),
        toEmail: $member->email,
    );

    Mail::assertNothingSent();
});
