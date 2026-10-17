<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('creates a staff profile', function (): void {
    Role::findOrCreate('super_admin', 'web');
    $this->actingAs(User::factory()->create()->assignRole('super_admin'));
    Filament::setCurrentPanel('admin');

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Taylor Morgan',
            'email' => 'taylor@example.com',
            'contact' => '+91 9000000001',
            'emergency_contact' => '+91 9000000002',
            'gender' => 'other',
            'dob' => '1995-04-10',
            'joining_date' => '2026-10-01',
            'address' => '12 Market Street',
            'country' => 'India',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    $staff = User::query()->where('email', 'taylor@example.com')->firstOrFail();

    expect($staff->emergency_contact)->toBe('+91 9000000002');
    expect($staff->joining_date?->toDateString())->toBe('2026-10-01');
});

it('creates staff profile details through the API', function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('Create:User', 'web');

    $actor = User::factory()->create();
    $actor->givePermissionTo('Create:User');
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/v1/users', [
        'name' => 'Morgan Lee',
        'email' => 'morgan@example.com',
        'contact' => '+91 9000000011',
        'emergency_contact' => '+91 9000000012',
        'gender' => 'female',
        'dob' => '1994-02-18',
        'joining_date' => '2026-09-15',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.emergency_contact', '+91 9000000012')
        ->assertJsonPath('data.joining_date', '2026-09-15');

    $staff = User::query()->where('email', 'morgan@example.com')->firstOrFail();

    expect($staff->emergency_contact)->toBe('+91 9000000012');
});

it('rejects a future staff joining date through the API', function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('Create:User', 'web');

    $actor = User::factory()->create();
    $actor->givePermissionTo('Create:User');
    Sanctum::actingAs($actor);

    $this->postJson('/api/v1/users', [
        'name' => 'Future Staff',
        'email' => 'future@example.com',
        'joining_date' => '2999-01-01',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('joining_date');
});
