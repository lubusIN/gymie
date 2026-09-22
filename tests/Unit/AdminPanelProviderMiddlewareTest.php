<?php

use App\Http\Middleware\SetAppLocale;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Panel;
use Illuminate\Cache\ArrayStore;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Collection;

it('includes the locale middleware in the admin panel stack', function (): void {
    $provider = new AdminPanelProvider(app());

    $panel = $provider->basePanel(Panel::make());

    expect($panel->getMiddleware())->toContain(SetAppLocale::class);
});

it('protects panel requests against forgery', function (): void {
    $provider = new AdminPanelProvider(app());

    $panel = $provider->basePanel(Panel::make());

    expect($panel->getMiddleware())->toContain(PreventRequestForgery::class)
        ->and(config('sanctum.middleware.validate_csrf_token'))->toBe(PreventRequestForgery::class);
});

it('only allows required classes to be restored from cache', function (): void {
    $store = new ArrayStore(
        serializesValues: true,
        serializableClasses: config('cache.serializable_classes'),
    );

    $store->put('countries', collect([['name' => 'India']]), 60);
    $store->put('unexpected-object', new stdClass, 60);

    expect(config('cache.serializable_classes'))->toBe([Collection::class])
        ->and($store->get('countries'))->toBeInstanceOf(Collection::class)
        ->and($store->get('unexpected-object'))->toBeInstanceOf(__PHP_Incomplete_Class::class)
        ->and(config('session.serialization'))->toBe('json');
});
