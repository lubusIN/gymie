<?php

use App\Contracts\SettingsRepository;
use App\Filament\Livewire\LocaleSwitcher;
use Livewire\Livewire;

it('persists the selected locale in settings', function (): void {
    config()->set('app.supported_locales', ['en', 'fr', 'ar']);

    $repository = new class implements SettingsRepository
    {
        /**
         * @var array<string, mixed>
         */
        public array $lastPut = [];

        public function get(): array
        {
            return [
                'general' => [
                    'locale' => 'en',
                ],
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
    app()->setLocale('en');

    Livewire::test(LocaleSwitcher::class)
        ->call('setLocale', 'fr')
        ->assertRedirect()
        ->assertSet('locale', 'fr')
        ->assertNotified('Langue mise à jour');

    expect(data_get($repository->lastPut, 'general.locale'))->toBe('fr');
});

it('ignores unsupported locales', function (): void {
    config()->set('app.supported_locales', ['en', 'fr', 'ar']);

    $repository = new class implements SettingsRepository
    {
        public int $putCount = 0;

        public function get(): array
        {
            return [
                'general' => [
                    'locale' => 'en',
                ],
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
            $this->putCount++;
        }
    };

    app()->instance(SettingsRepository::class, $repository);
    app()->setLocale('en');

    Livewire::test(LocaleSwitcher::class)
        ->call('setLocale', 'de')
        ->assertSet('locale', 'en');

    expect($repository->putCount)->toBe(0);
});

it('renders the current locale label in the trigger', function (): void {
    config()->set('app.supported_locales', ['en', 'fr', 'ar']);

    app()->setLocale('en');

    Livewire::test(LocaleSwitcher::class)
        ->assertSeeHtml('fi-locale-switcher-label')
        ->assertSee('English');
});
