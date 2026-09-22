<?php

use App\Contracts\SettingsRepository;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Models\Expense;
use App\Models\User;
use App\Support\Filament\ExpenseCategorySelect;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->repository = new class implements SettingsRepository
    {
        /** @var array<string, mixed> */
        public array $settings = [
            'general' => [
                'gym_name' => 'Gymie',
            ],
            'expenses' => [
                'categories' => [],
            ],
        ];

        public function get(): array
        {
            return $this->settings;
        }

        public function put(array $settings): void
        {
            $this->settings = $settings;
        }
    };

    app()->instance(SettingsRepository::class, $this->repository);
});

it('uses a compact inline category form', function (): void {
    app()->setLocale('en');

    $select = ExpenseCategorySelect::make('category');
    $schema = Schema::make()->components([$select]);
    $schema->getComponents();
    $action = $select->getCreateOptionAction();
    $form = $select->getCreateOptionActionForm($schema);
    $nameField = is_array($form) ? $form[0] ?? null : null;

    expect($action?->getModalWidth())->toBe(Width::Small)
        ->and($nameField)->toBeInstanceOf(TextInput::class)
        ->and($nameField?->getLabel())->toBe('Category')
        ->and($nameField?->getMaxLength())->toBe(100);
});

it('creates and selects a category without leaving the expense form', function (): void {
    Role::findOrCreate('super_admin', 'web');
    $this->actingAs(User::factory()->create()->assignRole('super_admin'));
    Filament::setCurrentPanel('admin');
    Expense::factory()->create();

    $component = Livewire::test(ListExpenses::class)
        ->mountAction('create');
    $schemaName = $component->instance()->getMountedActionSchemaName();

    $component->callFormComponentAction(
        'category',
        'createOption',
        ['name' => 'Staff Welfare'],
        formName: $schemaName,
    );

    expect(data_get($this->repository->settings, 'expenses.categories'))
        ->toContain('Rent', 'Staff Welfare')
        ->and(data_get($this->repository->settings, 'general.gym_name'))->toBe('Gymie');

    $component->assertActionDataSet([
        'category' => 'staff-welfare',
    ]);

    $component->callFormComponentAction(
        'category',
        'createOption',
        ['name' => 'staff welfare'],
        formName: $schemaName,
    );

    expect(collect(data_get($this->repository->settings, 'expenses.categories'))
        ->filter(fn (string $category): bool => strcasecmp($category, 'Staff Welfare') === 0))
        ->toHaveCount(1);
});

it('uses the correct submit labels for expense actions', function (): void {
    Role::findOrCreate('super_admin', 'web');
    $this->actingAs(User::factory()->create()->assignRole('super_admin'));
    Filament::setCurrentPanel('admin');
    app()->setLocale('en');

    Expense::factory()->create();

    $component = Livewire::test(ListExpenses::class);
    $headerAction = collect($component->instance()->getCachedHeaderActions())
        ->first(fn ($action): bool => $action->getName() === 'create');

    expect($headerAction)->toBeInstanceOf(CreateAction::class)
        ->and($headerAction->getLabel())->toBe('New Expense')
        ->and($headerAction->getModalHeading())->toBe('New Expense')
        ->and($headerAction->getModalSubmitActionLabel())->toBe('Create')
        ->and($headerAction->canCreateAnother())->toBeFalse();

    $editAction = $component->instance()->getTable()->getAction('edit');

    expect($editAction)->toBeInstanceOf(EditAction::class)
        ->and($editAction->getModalSubmitActionLabel())->toBe('Save changes');

    Expense::query()->delete();

    $emptyStateAction = collect(Livewire::test(ListExpenses::class)
        ->instance()
        ->getTable()
        ->getEmptyStateActions())
        ->first(fn ($action): bool => $action->getName() === 'create');

    expect($emptyStateAction)->toBeInstanceOf(CreateAction::class)
        ->and($emptyStateAction->getLabel())->toBe('New Expense')
        ->and($emptyStateAction->getModalHeading())->toBe('New Expense')
        ->and($emptyStateAction->getModalSubmitActionLabel())->toBe('Create')
        ->and($emptyStateAction->canCreateAnother())->toBeFalse();
});
