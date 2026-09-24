<?php

use App\Enums\Status;
use App\Filament\Resources\Enquiries\Pages\ListEnquiries;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Models\Enquiry;
use App\Models\FollowUp;
use App\Models\Member;
use App\Models\Plan;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('super_admin', 'web');
    $this->actingAs(User::factory()->create()->assignRole('super_admin'));
    Filament::setCurrentPanel('admin');
});

it('creates enquiries in the lead stage', function (): void {
    $this->postJson('/api/v1/enquiries', [
        'name' => 'Taylor Prospect',
        'email' => 'taylor@example.com',
        'contact' => '+91 9000000000',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', Status::Lead->value);

    $this->assertDatabaseHas('enquiries', [
        'email' => 'taylor@example.com',
        'status' => Status::Lead->value,
    ]);
});

it('keeps the enquiry pipeline aligned with pending follow-ups', function (): void {
    $enquiry = Enquiry::factory()->create(['status' => Status::Lead]);
    $firstFollowUp = FollowUp::factory()->create([
        'enquiry_id' => $enquiry->id,
        'schedule_date' => today()->addDay(),
        'status' => Status::Pending,
    ]);
    $secondFollowUp = FollowUp::factory()->create([
        'enquiry_id' => $enquiry->id,
        'schedule_date' => today()->addDays(2),
        'status' => Status::Pending,
    ]);

    expect($enquiry->refresh()->status)->toBe(Status::FollowUpScheduled)
        ->and($enquiry->nextFollowUp?->is($firstFollowUp))->toBeTrue();

    $firstFollowUp->update(['status' => Status::Done]);

    expect($enquiry->refresh()->status)->toBe(Status::FollowUpScheduled)
        ->and($enquiry->nextFollowUp?->is($secondFollowUp))->toBeTrue();

    $secondFollowUp->update(['status' => Status::Done]);

    expect($enquiry->refresh()->status)->toBe(Status::Lead)
        ->and($enquiry->nextFollowUp)->toBeNull();
});

it('schedules the next follow-up from the enquiry table', function (): void {
    $enquiry = Enquiry::factory()->create(['status' => Status::Lead]);
    $scheduledDate = today()->addDays(3)->toDateString();

    Livewire::test(ListEnquiries::class)
        ->callTableAction('schedule_follow_up', $enquiry, [
            'method' => 'call',
            'schedule_date' => $scheduledDate,
        ])
        ->assertNotified();

    $followUp = $enquiry->followUps()->sole();

    expect($followUp->user_id)->toBe($enquiry->user_id)
        ->and($followUp->method)->toBe('call')
        ->and($followUp->schedule_date?->toDateString())->toBe($scheduledDate)
        ->and($enquiry->refresh()->status)->toBe(Status::FollowUpScheduled);

    Livewire::test(ListEnquiries::class)
        ->assertTableActionHidden('schedule_follow_up', $enquiry);
});

it('requires and records a reason when an enquiry is lost', function (): void {
    $enquiry = Enquiry::factory()->create(['status' => Status::Lead]);

    Livewire::test(ListEnquiries::class)
        ->callTableAction('mark_as_lost', $enquiry, ['lost_reason' => null])
        ->assertHasTableActionErrors(['lost_reason' => 'required']);

    expect($enquiry->refresh()->status)->toBe(Status::Lead);

    Livewire::test(ListEnquiries::class)
        ->callTableAction('mark_as_lost', $enquiry, [
            'lost_reason' => 'Joined another gym',
        ])
        ->assertNotified();

    expect($enquiry->refresh()->status)->toBe(Status::Lost)
        ->and($enquiry->lost_reason)->toBe('Joined another gym');
});

it('links the converted member and closes the enquiry pipeline', function (): void {
    $enquiry = Enquiry::factory()->create(['status' => Status::Lead]);
    $member = Member::factory()->create();

    $enquiry->convertTo($member);

    expect($enquiry->refresh()->status)->toBe(Status::Member)
        ->and($enquiry->converted_at)->not->toBeNull()
        ->and($enquiry->member?->is($member))->toBeTrue()
        ->and($member->refresh()->enquiry?->is($enquiry))->toBeTrue();

    $anotherMember = Member::factory()->create();

    expect(fn () => $enquiry->convertTo($anotherMember))
        ->toThrow(LogicException::class, 'Only open enquiries can be converted.')
        ->and($anotherMember->refresh()->enquiry_id)->toBeNull();
});

it('converts an enquiry through the member creation flow', function (): void {
    $enquiry = Enquiry::factory()->create(['status' => Status::Lead]);
    $plan = Plan::factory()->create(['status' => Status::Active]);

    $component = Livewire::withQueryParams(['enquiry_id' => $enquiry->id])
        ->test(CreateMember::class);
    $subscriptionKey = array_key_first($component->get('data.subscriptions'));
    $invoiceKey = array_key_first($component->get("data.subscriptions.{$subscriptionKey}.invoices"));

    $component
        ->set('data.country', $enquiry->country)
        ->set('data.state', null)
        ->set('data.city', null)
        ->set("data.subscriptions.{$subscriptionKey}.plan_id", $plan->id)
        ->set("data.subscriptions.{$subscriptionKey}.invoices.{$invoiceKey}.due_date", today()->toDateString())
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $member = Member::query()->where('enquiry_id', $enquiry->id)->sole();

    expect($member->name)->toBe($enquiry->name)
        ->and($enquiry->refresh()->status)->toBe(Status::Member)
        ->and($enquiry->member?->is($member))->toBeTrue();
});

it('does not reopen a terminal enquiry when follow-ups change', function (Status $status): void {
    $enquiry = Enquiry::factory()->create([
        'status' => $status,
        'lost_reason' => $status === Status::Lost ? 'No longer interested' : null,
        'converted_at' => $status === Status::Member ? now() : null,
    ]);

    FollowUp::factory()->create([
        'enquiry_id' => $enquiry->id,
        'status' => Status::Pending,
    ]);

    expect($enquiry->refresh()->status)->toBe($status);
})->with([
    'member' => Status::Member,
    'lost' => Status::Lost,
]);

it('prevents clients from bypassing the initial pipeline state', function (): void {
    $this->postJson('/api/v1/enquiries', [
        'name' => 'Taylor Prospect',
        'email' => 'taylor@example.com',
        'contact' => '+91 9000000000',
        'status' => Status::Member->value,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    $this->assertDatabaseMissing('enquiries', ['email' => 'taylor@example.com']);
});

it('requires a loss reason when the pipeline is updated through the API', function (): void {
    $enquiry = Enquiry::factory()->create(['status' => Status::Lead]);

    $this->putJson("/api/v1/enquiries/{$enquiry->id}", [
        'status' => Status::Lost->value,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lost_reason');

    expect($enquiry->refresh()->status)->toBe(Status::Lead);

    $this->putJson("/api/v1/enquiries/{$enquiry->id}", [
        'status' => Status::Lost->value,
        'lost_reason' => 'No longer interested',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', Status::Lost->value)
        ->assertJsonPath('data.lost_reason', 'No longer interested');
});

it('hides pipeline write actions from users without update permission', function (): void {
    $role = Role::findOrCreate('enquiry_viewer', 'web');
    $role->givePermissionTo([
        Permission::findOrCreate('ViewAny:Enquiry', 'web'),
        Permission::findOrCreate('View:Enquiry', 'web'),
    ]);
    $this->actingAs(User::factory()->create()->assignRole($role));
    $enquiry = Enquiry::factory()->create(['status' => Status::Lead]);

    Livewire::test(ListEnquiries::class)
        ->assertTableActionHidden('schedule_follow_up', $enquiry)
        ->assertTableActionHidden('convert_to_member', $enquiry)
        ->assertTableActionHidden('mark_as_lost', $enquiry);
});
