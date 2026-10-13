<?php

use App\Enums\CommunicationDeliveryStatus;
use App\Enums\CommunicationRetryHandler;
use App\Enums\CommunicationTemplateKey;
use App\Helpers\Helpers;
use App\Jobs\SendCommunicationEmail;
use App\Mail\CommunicationMail;
use App\Models\CommunicationAutomation;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationTemplate;
use App\Models\Member;
use App\Services\Communication\CommunicationDeliveryTracker;
use App\Services\Communication\CommunicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'mail.default' => 'smtp',
        'mail.from.address' => 'notifications@gymie.test',
    ]);
});

afterEach(function (): void {
    Helpers::setTestSettingsOverride(null);
});

it('queues the welcome template when member welcome automation is enabled', function (): void {
    Queue::fake([SendCommunicationEmail::class]);
    memberWelcomeSettings(enabled: true);

    $member = Member::factory()->create([
        'name' => 'Taylor Member',
        'email' => 'taylor@example.com',
        'code' => 'MEM-001',
    ]);

    $delivery = CommunicationDelivery::query()->sole();

    expect($delivery->template->key)->toBe(CommunicationTemplateKey::MemberWelcome->value)
        ->and($delivery->recipient)->toBe('taylor@example.com')
        ->and($delivery->status)->toBe(CommunicationDeliveryStatus::Queued)
        ->and($delivery->related->is($member))->toBeTrue()
        ->and($delivery->retry_payload)->toBe([
            'handler' => CommunicationRetryHandler::Communication->value,
            'variables' => [
                'member_name' => 'Taylor Member',
                'member_code' => 'MEM-001',
            ],
        ]);

    Queue::assertPushed(SendCommunicationEmail::class, fn (SendCommunicationEmail $job): bool => $job->deliveryId === $delivery->getKey());
});

it('does not queue a welcome email when automation is disabled', function (): void {
    Queue::fake([SendCommunicationEmail::class]);
    memberWelcomeSettings(enabled: false);

    Member::factory()->create();

    expect(CommunicationDelivery::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('records an opted-out member email as skipped without dispatching it', function (): void {
    Queue::fake([SendCommunicationEmail::class]);
    memberWelcomeSettings(enabled: true);

    Member::factory()->create([
        'email' => 'taylor@example.com',
        'email_communication_enabled' => false,
    ]);

    $delivery = CommunicationDelivery::query()->sole();

    expect($delivery->status)->toBe(CommunicationDeliveryStatus::Skipped)
        ->and($delivery->failure_reason)->toBe(__('app.communication.member_email_opted_out'));

    Queue::assertNotPushed(SendCommunicationEmail::class);
});

it('records an automated welcome email as skipped when outbound delivery is unavailable', function (): void {
    Queue::fake([SendCommunicationEmail::class]);
    config(['mail.default' => 'log']);
    memberWelcomeSettings(enabled: true);

    Member::factory()->create([
        'email' => 'taylor@example.com',
    ]);

    $delivery = CommunicationDelivery::query()->sole();

    expect($delivery->status)->toBe(CommunicationDeliveryStatus::Skipped)
        ->and($delivery->failure_reason)->toBe(__('app.notifications.mail_not_configured'));

    Queue::assertNotPushed(SendCommunicationEmail::class);
});

it('skips a queued communication if outbound delivery becomes unavailable before processing', function (): void {
    Mail::fake();
    memberWelcomeSettings(enabled: false);
    $member = Member::factory()->create(['email' => 'taylor@example.com']);
    $delivery = app(CommunicationDeliveryTracker::class)->queued(
        CommunicationTemplateKey::MemberWelcome,
        $member->email,
        $member,
    );
    config(['mail.default' => 'log']);

    app(CommunicationService::class)->send((int) $delivery->getKey(), [
        'member_name' => $member->name,
        'member_code' => $member->code,
    ]);

    expect($delivery->refresh()->status)->toBe(CommunicationDeliveryStatus::Skipped)
        ->and($delivery->failure_reason)->toBe(__('app.notifications.mail_not_configured'));

    Mail::assertNothingSent();
});

it('honors an email opt-out made after a message was queued', function (): void {
    Mail::fake();
    memberWelcomeSettings(enabled: false);
    $member = Member::factory()->create(['email' => 'taylor@example.com']);
    $delivery = app(CommunicationDeliveryTracker::class)->queued(
        CommunicationTemplateKey::MemberWelcome,
        $member->email,
        $member,
    );
    $member->update(['email_communication_enabled' => false]);

    app(CommunicationService::class)->send((int) $delivery->getKey(), [
        'member_name' => $member->name,
        'member_code' => $member->code,
    ]);

    expect($delivery->refresh()->status)->toBe(CommunicationDeliveryStatus::Skipped)
        ->and($delivery->failure_reason)->toBe(__('app.communication.member_email_opted_out'));

    Mail::assertNothingSent();
});

it('renders and records a member welcome delivery', function (): void {
    memberWelcomeSettings(enabled: false);
    config(['mail.from.address' => 'notifications@gymie.test']);
    Mail::fake();
    $member = Member::factory()->create([
        'name' => 'Taylor Member',
        'email' => 'taylor@example.com',
        'code' => 'MEM-001',
    ]);
    $delivery = app(CommunicationDeliveryTracker::class)->queued(
        CommunicationTemplateKey::MemberWelcome,
        $member->email,
        $member,
    );

    app(CommunicationService::class)->send(
        deliveryId: (int) $delivery->getKey(),
        variables: [
            'member_name' => $member->name,
            'member_code' => $member->code,
        ],
    );

    expect($delivery->refresh()->status)->toBe(CommunicationDeliveryStatus::Sent)
        ->and($delivery->subject)->toBe('Welcome to Demo Gym');

    Mail::assertSent(CommunicationMail::class, fn (CommunicationMail $mail): bool => $mail->messageBody === 'Hi Taylor Member, welcome to Demo Gym. Your member ID is MEM-001.');
});

it('renders markdown communication content without allowing unsafe html', function (): void {
    $mail = new CommunicationMail(
        subjectLine: 'Welcome',
        messageBody: "**Welcome** to Gymie.\n\n<script>alert('unsafe')</script>",
        fromAddress: 'notifications@gymie.test',
        gymName: 'Demo Gym',
        gymEmail: 'gym@example.com',
        gymContact: '9999999999',
    );

    expect($mail->render())
        ->toContain('<strong>Welcome</strong>')
        ->not->toContain('<script>');
});

function memberWelcomeSettings(bool $enabled): void
{
    Helpers::setTestSettingsOverride([
        'general' => [
            'gym_name' => 'Demo Gym',
            'gym_email' => 'gym@example.com',
            'gym_contact' => '9999999999',
        ],
        'notifications' => [
            'email' => [
                'enabled' => true,
            ],
        ],
    ]);

    CommunicationAutomation::query()->create([
        'event' => CommunicationTemplateKey::MemberWelcome,
        'communication_template_id' => CommunicationTemplate::query()
            ->where('key', CommunicationTemplateKey::MemberWelcome->value)
            ->value('id'),
        'is_enabled' => $enabled,
    ]);
}
