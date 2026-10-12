<?php

use App\Enums\CommunicationTemplateKey;
use App\Enums\Status;
use App\Filament\Resources\CommunicationTemplates\Pages\ListCommunicationTemplates;
use App\Helpers\Helpers;
use App\Jobs\SendCommunicationEmail;
use App\Models\CommunicationAutomation;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationTemplate;
use App\Models\Enquiry;
use App\Models\FollowUp;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Communication\CommunicationAutomationService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'mail.default' => 'smtp',
        'mail.from.address' => 'notifications@gymie.test',
    ]);

    Role::findOrCreate('super_admin', 'web');
    $this->actingAs(User::factory()->create()->assignRole('super_admin'));
    Filament::setCurrentPanel('admin');

    Helpers::setTestSettingsOverride([
        'general' => [
            'gym_name' => 'Demo Gym',
            'gym_email' => 'gym@example.com',
            'gym_contact' => '9999999999',
        ],
        'notifications' => ['email' => ['enabled' => true]],
    ]);
});

it('prevents automatic delivery from being enabled without an outbound mailer', function (): void {
    config(['mail.default' => 'log']);
    $template = CommunicationTemplate::query()
        ->where('key', CommunicationTemplateKey::MemberWelcome->value)
        ->sole();

    expect(fn () => app(CommunicationAutomationService::class)->setAutomaticDelivery($template, true))
        ->toThrow(ValidationException::class, __('app.notifications.mail_not_configured'));

    expect(CommunicationAutomation::query()->count())->toBe(0);
});

afterEach(function (): void {
    Helpers::setTestSettingsOverride(null);
});

it('uses the predefined template until an event is configured', function (): void {
    $template = CommunicationTemplate::query()
        ->where('key', CommunicationTemplateKey::MemberWelcome->value)
        ->sole();

    expect(CommunicationAutomation::query()->count())->toBe(0)
        ->and(CommunicationTemplateKey::cases())->toHaveCount(10)
        ->and($template->isUsedForEvent())->toBeTrue()
        ->and($template->isAutomaticDeliveryEnabled())->toBeFalse();
});

it('lets an owner select a compatible template for an automatic event', function (): void {
    Queue::fake([SendCommunicationEmail::class]);
    $customTemplate = CommunicationTemplate::factory()->custom()->create([
        'name' => 'Personal welcome',
        'subject' => 'Welcome {{ member_name }}',
        'body' => 'Your member ID is {{ member_code }}.',
    ]);
    Livewire::test(ListCommunicationTemplates::class)
        ->callTableAction('use_for_event', $customTemplate)
        ->callTableAction('delivery_settings', $customTemplate, [
            'send_automatically' => true,
        ]);

    $automation = CommunicationAutomation::query()->sole();
    $member = Member::factory()->create([
        'name' => 'Taylor Member',
        'email' => 'taylor@example.com',
        'code' => 'MEM-001',
    ]);
    $delivery = CommunicationDelivery::query()->sole();

    expect($automation->refresh()->is_enabled)->toBeTrue()
        ->and($automation->template->is($customTemplate))->toBeTrue()
        ->and($delivery->template->is($customTemplate))->toBeTrue()
        ->and($delivery->related->is($member))->toBeTrue();
});

it('keeps one selected template per event', function (): void {
    $firstTemplate = CommunicationTemplate::factory()->custom()->create([
        'name' => 'First welcome',
    ]);
    $secondTemplate = CommunicationTemplate::factory()->custom()->create([
        'name' => 'Second welcome',
    ]);

    Livewire::test(ListCommunicationTemplates::class)
        ->callTableAction('use_for_event', $firstTemplate)
        ->callTableAction('use_for_event', $secondTemplate);

    $configuration = CommunicationAutomation::query()->sole();

    expect($configuration->template->is($secondTemplate))->toBeTrue();
});

it('rejects a template created for a different event', function (): void {
    $template = CommunicationTemplate::factory()->custom()->create([
        'event' => CommunicationTemplateKey::InvoiceIssued,
        'subject' => 'Invoice {{ invoice_number }}',
        'body' => 'Invoice {{ invoice_number }}',
    ]);
    expect(fn () => CommunicationAutomation::query()->create([
        'event' => CommunicationTemplateKey::MemberWelcome,
        'communication_template_id' => $template->getKey(),
        'is_enabled' => true,
    ]))->toThrow(ValidationException::class);
});

it('prevents changing a template event while an automation uses it', function (): void {
    $template = CommunicationTemplate::factory()->custom()->create();
    CommunicationAutomation::query()->create([
        'event' => CommunicationTemplateKey::MemberWelcome,
        'communication_template_id' => $template->getKey(),
        'is_enabled' => false,
    ]);

    expect(fn () => $template->update([
        'event' => CommunicationTemplateKey::InvoiceIssued,
    ]))->toThrow(ValidationException::class, 'event cannot be changed');
});

it('deletes custom templates and restores affected events to their defaults', function (): void {
    $customTemplate = CommunicationTemplate::factory()->custom()->create();
    $automation = CommunicationAutomation::query()->create([
        'event' => CommunicationTemplateKey::MemberWelcome,
        'communication_template_id' => $customTemplate->getKey(),
        'is_enabled' => true,
    ]);

    Livewire::test(ListCommunicationTemplates::class)
        ->assertTableActionVisible('delete', $customTemplate)
        ->callTableAction('delete', $customTemplate);

    expect(CommunicationTemplate::onlyTrashed()->find($customTemplate->getKey()))->not->toBeNull()
        ->and($automation->refresh()->template->key)->toBe(CommunicationTemplateKey::MemberWelcome->value);
});

it('lets an owner restore the predefined template for an event', function (): void {
    $defaultTemplate = CommunicationTemplate::query()
        ->where('key', CommunicationTemplateKey::MemberWelcome->value)
        ->sole();
    $customTemplate = CommunicationTemplate::factory()->custom()->create();
    CommunicationAutomation::query()->create([
        'event' => CommunicationTemplateKey::MemberWelcome,
        'communication_template_id' => $customTemplate->getKey(),
        'is_enabled' => true,
    ]);

    Livewire::test(ListCommunicationTemplates::class)
        ->callTableAction('use_for_event', $defaultTemplate);

    $configuration = CommunicationAutomation::query()->sole();

    expect($configuration->template->is($defaultTemplate))->toBeTrue()
        ->and($configuration->is_enabled)->toBeTrue();
});

it('queues membership expiry and email follow-up events when their automations are enabled', function (): void {
    Queue::fake([SendCommunicationEmail::class]);
    foreach ([CommunicationTemplateKey::MembershipExpiring, CommunicationTemplateKey::EnquiryFollowUp] as $event) {
        CommunicationAutomation::query()->create([
            'event' => $event,
            'communication_template_id' => CommunicationTemplate::query()->where('key', $event->value)->value('id'),
            'is_enabled' => true,
        ]);
    }

    $member = Member::factory()->create(['email' => 'member@example.com']);
    $plan = Plan::factory()->create(['name' => 'Growth']);
    $subscription = Subscription::factory()->for($member)->for($plan)->current()->create();
    $subscription->update(['status' => Status::Expiring]);

    $enquiry = Enquiry::factory()->create([
        'email' => 'lead@example.com',
        'interested_in' => ['Strength training'],
    ]);
    $followUp = FollowUp::factory()->for($enquiry)->create(['method' => 'email']);

    $deliveries = CommunicationDelivery::query()->with('template')->get();

    expect($deliveries)->toHaveCount(2)
        ->and($deliveries->pluck('template.key')->sort()->values()->all())->toBe([
            CommunicationTemplateKey::EnquiryFollowUp->value,
            CommunicationTemplateKey::MembershipExpiring->value,
        ])
        ->and($deliveries->pluck('related_type')->all())->toContain(
            $subscription->getMorphClass(),
            $followUp->getMorphClass(),
        );

    Queue::assertPushed(SendCommunicationEmail::class, 2);
});

it('keeps payment reminders manual even when enabled directly', function (): void {
    $event = CommunicationTemplateKey::PaymentReminder;
    $template = CommunicationTemplate::query()->where('key', $event->value)->sole();

    app(CommunicationAutomationService::class)->setAutomaticDelivery($template, true);

    $automation = CommunicationAutomation::query()->sole();

    expect($automation->refresh()->is_enabled)->toBeFalse();
});

it('queues renewal confirmations and expiration notices when enabled', function (): void {
    Queue::fake([SendCommunicationEmail::class]);

    foreach ([CommunicationTemplateKey::MembershipRenewed, CommunicationTemplateKey::MembershipExpired] as $event) {
        CommunicationAutomation::query()->create([
            'event' => $event,
            'communication_template_id' => CommunicationTemplate::query()->where('key', $event->value)->value('id'),
            'is_enabled' => true,
        ]);
    }

    $member = Member::factory()->create(['email' => 'member@example.com']);
    $plan = Plan::factory()->create(['name' => 'Growth']);
    $expiredSubscription = Subscription::factory()->for($member)->for($plan)->create([
        'start_date' => now()->subMonths(2),
        'end_date' => now()->subMonth(),
        'status' => Status::Expired,
    ]);
    Subscription::factory()->for($member)->for($plan)->create([
        'renewed_from_subscription_id' => $expiredSubscription->getKey(),
        'start_date' => now(),
        'end_date' => now()->addMonth(),
        'status' => Status::Ongoing,
    ]);

    $endingSubscription = Subscription::factory()->for($member)->for($plan)->current()->create();
    $endingSubscription->update(['status' => Status::Expired]);

    expect(CommunicationDelivery::query()->with('template')->get()->pluck('template.key')->sort()->values()->all())->toBe([
        CommunicationTemplateKey::MembershipExpired->value,
        CommunicationTemplateKey::MembershipRenewed->value,
    ]);

    Queue::assertPushed(SendCommunicationEmail::class, 2);
});
