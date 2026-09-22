<?php

use App\Filament\Resources\FollowUps\Pages\ListFollowUps;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('uses a title-cased label for follow-up creation', function (): void {
    Role::findOrCreate('super_admin', 'web');
    $this->actingAs(User::factory()->create()->assignRole('super_admin'));
    Filament::setCurrentPanel('admin');
    app()->setLocale('en');

    $component = Livewire::test(ListFollowUps::class);
    $headerAction = collect($component->instance()->getCachedHeaderActions())
        ->first(fn ($action): bool => $action->getName() === 'create');
    $emptyStateAction = collect($component->instance()->getTable()->getEmptyStateActions())
        ->first(fn ($action): bool => $action->getName() === 'create');

    expect($headerAction)->toBeInstanceOf(CreateAction::class)
        ->and($headerAction->getLabel())->toBe('New Follow Up')
        ->and($headerAction->getModalHeading())->toBe('New Follow Up')
        ->and($emptyStateAction)->toBeInstanceOf(CreateAction::class)
        ->and($emptyStateAction->getLabel())->toBe('New Follow Up')
        ->and($emptyStateAction->getModalHeading())->toBe('New Follow Up');
});
