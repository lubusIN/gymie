<?php

use App\Enums\Status;
use App\Filament\Resources\Enquiries\Pages\CreateEnquiry;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Models\Enquiry;
use App\Models\Member;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('en');
    Role::findOrCreate('super_admin', 'web');
    $this->user = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($this->user);
    Filament::setCurrentPanel('admin');
});

it('links to an existing member when an enquiry email or contact matches', function (): void {
    $member = Member::factory()->create([
        'name' => 'Existing Member',
        'email' => 'existing@example.com',
        'contact' => '+91 9000000000',
        'status' => Status::Active,
    ]);

    Livewire::test(CreateEnquiry::class)
        ->set('data.email', 'existing@example.com')
        ->assertSee('This email matches an existing record. Review it before continuing.')
        ->assertSee('Check record')
        ->assertDontSee('Possible duplicate found')
        ->assertDontSee('Existing Member · Member · Active')
        ->assertSee(MemberResource::getUrl('view', ['record' => $member]))
        ->set('data.email', 'different@example.com')
        ->set('data.contact', '+91 9000000000')
        ->assertSee('Check record');
});

it('links to an existing enquiry when a member email matches', function (): void {
    Enquiry::factory()->create([
        'name' => 'Existing Lead',
        'email' => 'lead@example.com',
        'status' => Status::Lead,
    ]);

    Livewire::test(CreateMember::class)
        ->set('data.email', 'lead@example.com')
        ->assertSee('This email matches an existing record. Review it before continuing.')
        ->assertSee('Check record')
        ->assertDontSee('Possible duplicate found')
        ->assertDontSee('Existing Lead · Enquiry · Lead');
});

it('does not flag the source enquiry during member conversion', function (): void {
    $enquiry = Enquiry::factory()->create([
        'email' => 'conversion@example.com',
        'contact' => '+91 9111111111',
        'status' => Status::Lead,
    ]);

    Livewire::withQueryParams(['enquiry_id' => $enquiry->getKey()])
        ->test(CreateMember::class)
        ->assertDontSee('Check record');
});

it('does not flag the member being edited as a duplicate', function (): void {
    $member = Member::factory()->create([
        'email' => 'current@example.com',
        'contact' => '+91 9333333333',
    ]);

    Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
        ->assertDontSee('Check record');
});

it('allows shared contact details after linking to the existing record', function (): void {
    Enquiry::factory()->create([
        'email' => 'family@example.com',
        'contact' => '+91 9222222222',
    ]);

    $this->postJson('/api/v1/enquiries', [
        'name' => 'Family Member',
        'email' => 'family@example.com',
        'contact' => '+91 9222222222',
    ])->assertSuccessful();

    expect(Enquiry::query()->where('email', 'family@example.com')->count())->toBe(2);
});

it('does not reveal a matching record without permission to view it', function (): void {
    $member = Member::factory()->create([
        'name' => '<script>alert("private")</script>',
        'email' => 'private@example.com',
    ]);

    $permissions = collect(['ViewAny:Enquiry', 'Create:Enquiry'])
        ->map(fn (string $permission): Permission => Permission::findOrCreate($permission, 'web'));
    $restrictedUser = User::factory()->create()->givePermissionTo($permissions);
    $this->actingAs($restrictedUser);

    Livewire::test(CreateEnquiry::class)
        ->set('data.email', 'private@example.com')
        ->assertDontSee('Check record')
        ->assertDontSee(MemberResource::getUrl('view', ['record' => $member]))
        ->assertDontSee('alert("private")');
});
