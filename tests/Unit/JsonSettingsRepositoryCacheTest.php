<?php

use App\Contracts\SettingsRepository;
use App\Helpers\Helpers;

beforeEach(function (): void {
    Helpers::setTestSettingsOverride(null);
});

afterEach(function (): void {
    Helpers::setTestSettingsOverride(null);
});

it('returns updated settings after put() within the same request', function (): void {
    /** @var SettingsRepository $repo */
    $repo = app(SettingsRepository::class);

    $original = $repo->get();
    expect(data_get($original, 'general.gym_name'))->toBeString();

    $repo->put([
        ...$original,
        'general' => [
            ...($original['general'] ?? []),
            'gym_name' => 'IronPulse Gym',
        ],
    ]);

    $updated = $repo->get();

    expect(data_get($updated, 'general.gym_name'))->toBe('IronPulse Gym');
});

it('normalizes incomplete settings required by the settings form', function (): void {
    Helpers::setTestSettingsOverride([
        'charges' => ['discounts' => null],
        'expenses' => ['categories' => null],
        'subscriptions' => ['expiring_days' => null],
    ]);

    $settings = app(SettingsRepository::class)->get();

    expect(data_get($settings, 'charges.discounts'))->toBe([])
        ->and(data_get($settings, 'expenses.categories'))->toBe([])
        ->and(data_get($settings, 'subscriptions.expiring_days'))->toBe(7);
});
