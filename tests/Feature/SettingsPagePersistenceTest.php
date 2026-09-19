<?php

use App\Contracts\SettingsRepository;
use App\Filament\Pages\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('targets the save action while the form processes files', function (): void {
    Livewire::test(Settings::class)
        ->assertSeeHtml('x-data="filamentFormButton"')
        ->assertSeeHtml('wire:target="save"');
});

it('persists settings via the settings repository when saving', function (): void {
    $repository = new class implements SettingsRepository
    {
        /**
         * @var array<string, mixed>
         */
        public array $lastPut = [];

        public function get(): array
        {
            return [
                'general' => [],
                'invoice' => [],
                'member' => [],
                'charges' => [],
                'expenses' => [],
                'subscriptions' => [],
                'payments' => [],
                'notifications' => [
                    'email' => [],
                ],
            ];
        }

        public function put(array $settings): void
        {
            $this->lastPut = $settings;
        }
    };

    app()->instance(SettingsRepository::class, $repository);

    Livewire::test(Settings::class)
        ->fillForm([
            'general' => [
                'financial_year_start' => '2026-04-15',
                'financial_year_end' => null,
                'gym_logo' => ['images/logo.png'],
            ],
            'invoice' => [],
            'member' => [],
            'charges' => [
                'discounts' => [],
            ],
            'expenses' => [
                'categories' => [],
            ],
            'subscriptions' => [
                'expiring_days' => 7,
            ],
            'payments' => [],
            'notifications' => [
                'email' => [],
            ],
        ])
        ->call('save');

    expect($repository->lastPut)
        ->toHaveKey('general')
        ->and($repository->lastPut['general']['financial_year_start'])->toBe('2026-04-01')
        ->and($repository->lastPut['general']['financial_year_end'])->toBe('2027-03-31')
        ->and($repository->lastPut['general']['gym_logo'])->toBe('images/logo.png');
});

it('validates the Filament form before persisting settings', function (): void {
    $repository = new class implements SettingsRepository
    {
        /** @var array<string, mixed> */
        public array $lastPut = [];

        public function get(): array
        {
            return [
                'general' => [],
                'invoice' => [],
                'member' => [],
                'charges' => [],
                'expenses' => [],
                'subscriptions' => [],
                'payments' => [],
                'notifications' => ['email' => []],
            ];
        }

        public function put(array $settings): void
        {
            $this->lastPut = $settings;
        }
    };

    app()->instance(SettingsRepository::class, $repository);

    Livewire::test(Settings::class)
        ->fillForm([
            'subscriptions' => [
                'expiring_days' => null,
            ],
        ])
        ->call('save')
        ->assertHasFormErrors([
            'subscriptions.expiring_days' => 'required',
        ]);

    expect($repository->lastPut)->toBe([]);
});

it('shows saved settings when the page is mounted again', function (): void {
    $repository = new class implements SettingsRepository
    {
        /** @var array<string, mixed> */
        public array $settings = [
            'general' => [],
            'invoice' => [],
            'member' => [],
            'charges' => ['discounts' => []],
            'expenses' => ['categories' => []],
            'subscriptions' => ['expiring_days' => 7],
            'payments' => [],
            'notifications' => ['email' => []],
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

    app()->instance(SettingsRepository::class, $repository);

    Livewire::test(Settings::class)
        ->fillForm([
            'general.gym_name' => 'Reloaded Gym',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    Livewire::test(Settings::class)
        ->assertFormSet([
            'general.gym_name' => 'Reloaded Gym',
        ]);
});

it('stores and reloads the gym logo', function (): void {
    Storage::fake('public');

    $repository = new class implements SettingsRepository
    {
        /** @var array<string, mixed> */
        public array $settings = [
            'general' => [],
            'invoice' => [],
            'member' => [],
            'charges' => ['discounts' => []],
            'expenses' => ['categories' => []],
            'subscriptions' => ['expiring_days' => 7],
            'payments' => [],
            'notifications' => ['email' => []],
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

    app()->instance(SettingsRepository::class, $repository);

    $component = Livewire::test(Settings::class)
        ->set('data.general.gym_logo', UploadedFile::fake()->create('gym-logo.png', 100, 'image/png'));

    $component->call('save')
        ->assertHasNoFormErrors();

    $logoPath = data_get($repository->settings, 'general.gym_logo');

    expect($logoPath)->toBeString()
        ->and($component->get('data.general.gym_logo'))->toBeArray();
    Storage::disk('public')->assertExists($logoPath);

    $reloadedComponent = Livewire::test(Settings::class)
        ->assertFormSet([
            'general.gym_logo' => $logoPath,
        ]);

    expect($reloadedComponent->get('data.general.gym_logo'))->toBeArray();
});
