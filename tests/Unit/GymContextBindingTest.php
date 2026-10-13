<?php

use App\Contracts\GymContext;
use App\Providers\AppServiceProvider;
use App\Services\LocalGymContext;
use Illuminate\Foundation\Application;

it('registers the local gym context for standalone installations', function (): void {
    $application = new Application;

    (new AppServiceProvider($application))->register();

    $gymContext = $application->make(GymContext::class);

    expect($gymContext)
        ->toBeInstanceOf(LocalGymContext::class)
        ->and($gymContext->gymId())->toBeNull()
        ->and($application->make(GymContext::class))->toBe($gymContext);
});

it('preserves a gym context registered by an add-on', function (): void {
    $application = new Application;
    $gymContext = new class implements GymContext
    {
        public function gymId(): ?int
        {
            return 42;
        }
    };

    $application->singleton(GymContext::class, fn (): GymContext => $gymContext);

    (new AppServiceProvider($application))->register();

    expect($application->make(GymContext::class))
        ->toBe($gymContext)
        ->and($application->make(GymContext::class)->gymId())->toBe(42);
});
