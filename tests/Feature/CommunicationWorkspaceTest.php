<?php

use App\Enums\CommunicationDeliveryStatus;
use App\Enums\CommunicationTemplateKey;
use App\Filament\Resources\CommunicationDeliveries\CommunicationDeliveryResource;
use App\Filament\Resources\CommunicationDeliveries\Pages\ListCommunicationDeliveries;
use App\Filament\Resources\CommunicationTemplates\CommunicationTemplateResource;
use App\Filament\Resources\CommunicationTemplates\Pages\CreateCommunicationTemplate;
use App\Filament\Resources\CommunicationTemplates\Pages\EditCommunicationTemplate;
use App\Filament\Resources\CommunicationTemplates\Pages\ListCommunicationTemplates;
use App\Jobs\SendCommunicationEmail;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationTemplate;
use App\Models\User;
use App\Services\Communication\CommunicationTemplateRenderer;
use App\Services\Communication\RetryCommunicationDelivery;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('super_admin', 'web');
    $this->user = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($this->user);
    Filament::setCurrentPanel('admin');
});

it('installs the supported system templates', function (): void {
    expect(CommunicationTemplate::query()->count())->toBe(10)
        ->and(CommunicationTemplate::query()->pluck('key')->sort()->values()->all())->toBe([
            CommunicationTemplateKey::EnquiryFollowUp->value,
            CommunicationTemplateKey::InvoiceIssued->value,
            CommunicationTemplateKey::InvoiceOverdue->value,
            CommunicationTemplateKey::MemberInactive->value,
            CommunicationTemplateKey::MemberWelcome->value,
            CommunicationTemplateKey::MembershipExpired->value,
            CommunicationTemplateKey::MembershipExpiring->value,
            CommunicationTemplateKey::MembershipRenewed->value,
            CommunicationTemplateKey::PaymentReceipt->value,
            CommunicationTemplateKey::PaymentReminder->value,
        ]);
});

it('allows system template content to be edited without allowing its deletion', function (): void {
    $template = CommunicationTemplate::query()
        ->where('key', CommunicationTemplateKey::InvoiceIssued->value)
        ->sole();

    Livewire::test(EditCommunicationTemplate::class, ['record' => $template->getRouteKey()])
        ->fillForm([
            'subject' => 'Your invoice {{ invoice_number }}',
            'body' => 'Hi {{ member_name }}, {{ due }} is due.',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($template->refresh())
        ->subject->toBe('Your invoice {{ invoice_number }}')
        ->body->toBe('Hi {{ member_name }}, {{ due }} is due.')
        ->isSystem()->toBeTrue()
        ->and(CommunicationTemplateResource::getPages())->toHaveKey('create')
        ->and(CommunicationDeliveryResource::getPages())->not->toHaveKeys(['create', 'edit']);
});

it('creates custom templates with an application-generated key', function (): void {
    Livewire::test(CreateCommunicationTemplate::class)
        ->fillForm([
            'name' => 'Birthday greeting',
            'event' => CommunicationTemplateKey::MemberWelcome->value,
            'channel' => 'email',
            'subject' => 'Happy birthday {{ member_name }}',
            'body' => 'We hope you have a great day, {{ member_name }}.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $template = CommunicationTemplate::query()->where('name', 'Birthday greeting')->sole();

    expect($template->key)->toStartWith('custom_')
        ->and($template->isSystem())->toBeFalse()
        ->and($template->event)->toBe(CommunicationTemplateKey::MemberWelcome)
        ->and($template->availableVariables())->toBe(['member_name', 'member_code', 'gym_name']);
});

it('recovers variables for legacy templates without a stored event', function (string $key, array $variables): void {
    $template = CommunicationTemplate::factory()->make([
        'key' => $key,
        'event' => null,
    ]);

    expect($template->availableVariables())->toBe($variables);
})->with([
    'built-in template' => [
        CommunicationTemplateKey::MemberWelcome->value,
        ['member_name', 'member_code', 'gym_name'],
    ],
    'custom template' => [
        'custom_legacy_template',
        [],
    ],
]);

it('rejects unsupported template variables', function (): void {
    $template = CommunicationTemplate::query()
        ->where('key', CommunicationTemplateKey::InvoiceIssued->value)
        ->sole();

    expect(fn () => $template->update([
        'subject' => 'Invoice {{ secret_value }}',
    ]))->toThrow(ValidationException::class, 'Unsupported variables: secret_value');
});

it('renders the template and delivery history workspaces', function (): void {
    $delivery = CommunicationDelivery::factory()->create();

    Livewire::test(ListCommunicationTemplates::class)
        ->assertCanSeeTableRecords(CommunicationTemplate::query()->get());

    Livewire::test(ListCommunicationDeliveries::class)
        ->assertCanSeeTableRecords([$delivery]);
});

it('opens a template row in the view modal instead of the edit page', function (): void {
    $template = CommunicationTemplate::query()->firstOrFail();
    $table = Livewire::test(ListCommunicationTemplates::class)->instance()->getTable();

    expect($table->getRecordAction($template))->toBe('view')
        ->and($table->getRecordUrl($template))->toBeNull();
});

it('previews a template with safe samples and queues a marked test delivery', function (): void {
    config([
        'mail.default' => 'smtp',
        'mail.from.address' => 'notifications@gymie.test',
    ]);
    Queue::fake([SendCommunicationEmail::class]);
    $template = CommunicationTemplate::query()
        ->where('key', CommunicationTemplateKey::MemberWelcome->value)
        ->sole();
    $preview = app(CommunicationTemplateRenderer::class)->render(
        $template,
        $template->eventKey()?->sampleVariables() ?? [],
    );

    expect($preview)
        ->subject->toBe('Welcome to Demo Gym')
        ->body->toContain('Taylor Member')
        ->body->not->toContain('{{');

    Livewire::test(ListCommunicationTemplates::class)
        ->assertTableActionVisible('preview_and_test', $template)
        ->callTableAction('preview_and_test', $template, [
            'recipient' => 'owner@example.com',
        ])
        ->assertHasNoTableActionErrors()
        ->assertNotified(__('app.communication.test_delivery_queued'));

    $delivery = CommunicationDelivery::query()->sole();

    expect($delivery)
        ->is_test->toBeTrue()
        ->recipient->toBe('owner@example.com')
        ->related->toBeNull()
        ->and($delivery->retry_payload['variables'])->toMatchArray([
            'member_name' => 'Taylor Member',
            'member_code' => 'MEM-001',
            'gym_name' => 'Demo Gym',
        ]);

    Livewire::test(ListCommunicationDeliveries::class)
        ->assertSee(__('app.communication.test_delivery'));

    Queue::assertPushed(SendCommunicationEmail::class, fn (SendCommunicationEmail $job): bool => $job->deliveryId === $delivery->getKey());
});

it('does not queue a test delivery when outbound mail is unavailable', function (): void {
    config(['mail.default' => 'log']);
    Queue::fake([SendCommunicationEmail::class]);
    $template = CommunicationTemplate::query()->firstOrFail();

    Livewire::test(ListCommunicationTemplates::class)
        ->callTableAction('preview_and_test', $template, [
            'recipient' => 'owner@example.com',
        ])
        ->assertNotified(__('app.notifications.failed'));

    expect(CommunicationDelivery::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('validates the test recipient before queueing a template', function (): void {
    config([
        'mail.default' => 'smtp',
        'mail.from.address' => 'notifications@gymie.test',
    ]);
    Queue::fake([SendCommunicationEmail::class]);
    $template = CommunicationTemplate::query()->firstOrFail();

    Livewire::test(ListCommunicationTemplates::class)
        ->callTableAction('preview_and_test', $template, [
            'recipient' => 'not-an-email',
        ])
        ->assertHasTableActionErrors(['recipient' => 'email']);

    expect(CommunicationDelivery::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('opens a delivery row in a view modal without exposing a view page', function (): void {
    $delivery = CommunicationDelivery::factory()->create();
    $table = Livewire::test(ListCommunicationDeliveries::class)->instance()->getTable();

    expect($table->getRecordAction($delivery))->toBe('view')
        ->and($table->getRecordUrl($delivery))->toBeNull()
        ->and(CommunicationDeliveryResource::getPages())->not->toHaveKey('view');
});

it('allows authorized owners to delete delivery history records', function (): void {
    $delivery = CommunicationDelivery::factory()->create();

    Livewire::test(ListCommunicationDeliveries::class)
        ->assertTableActionVisible('delete', $delivery)
        ->callTableAction('delete', $delivery);

    $this->assertModelMissing($delivery);
});

it('shows retry only for authorized failed deliveries with retry context', function (): void {
    config([
        'mail.default' => 'smtp',
        'mail.from.address' => 'notifications@gymie.test',
    ]);
    Queue::fake([SendCommunicationEmail::class]);
    $failedDelivery = CommunicationDelivery::factory()->create([
        'status' => CommunicationDeliveryStatus::Failed,
        'failed_at' => now(),
        'retry_payload' => [
            'handler' => RetryCommunicationDelivery::COMMUNICATION_HANDLER,
            'variables' => [],
        ],
    ]);
    $queuedDelivery = CommunicationDelivery::factory()->create();

    Livewire::test(ListCommunicationDeliveries::class)
        ->assertTableActionVisible('retry', $failedDelivery)
        ->assertTableActionHidden('retry', $queuedDelivery)
        ->callTableAction('retry', $failedDelivery)
        ->assertHasNoTableActionErrors();

    expect($failedDelivery->refresh()->status)->toBe(CommunicationDeliveryStatus::Queued);
    Queue::assertPushed(SendCommunicationEmail::class);
});

it('protects templates and delivery history with dedicated permissions', function (): void {
    $defaultTemplate = CommunicationTemplate::query()
        ->where('key', CommunicationTemplateKey::MemberWelcome->value)
        ->sole();
    $customTemplate = CommunicationTemplate::factory()->custom()->create();
    $delivery = CommunicationDelivery::factory()->create([
        'status' => CommunicationDeliveryStatus::Failed,
        'failed_at' => now(),
        'retry_payload' => [
            'handler' => RetryCommunicationDelivery::COMMUNICATION_HANDLER,
            'variables' => [],
        ],
    ]);
    $viewer = User::factory()->create();
    $this->actingAs($viewer);

    $this->get(CommunicationTemplateResource::getUrl())->assertForbidden();
    $this->get(CommunicationDeliveryResource::getUrl())->assertForbidden();

    $viewer->givePermissionTo([
        Permission::findOrCreate('ViewAny:CommunicationTemplate', 'web'),
        Permission::findOrCreate('ViewAny:CommunicationDelivery', 'web'),
    ]);

    $this->get(CommunicationTemplateResource::getUrl())->assertSuccessful();
    $this->get(CommunicationDeliveryResource::getUrl())->assertSuccessful();

    Livewire::test(ListCommunicationTemplates::class)
        ->assertTableActionHidden('preview_and_test', $defaultTemplate)
        ->assertTableActionHidden('delivery_settings', $defaultTemplate)
        ->assertTableActionHidden('use_for_event', $customTemplate);

    Livewire::test(ListCommunicationDeliveries::class)
        ->assertTableActionHidden('retry', $delivery)
        ->assertTableActionHidden('delete', $delivery);
});
