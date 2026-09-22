<?php

use App\Contracts\SettingsRepository;
use App\Http\Middleware\SetAppLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

test('it syncs Carbon locale with app locale', function (): void {
    $originalAppLocale = app()->getLocale();
    $originalCarbonLocale = Carbon::getLocale();

    try {
        config()->set('app.locale', 'en');
        config()->set('app.fallback_locale', 'en');
        config()->set('app.supported_locales', ['en', 'fr', 'ar']);

        app()->bind(SettingsRepository::class, fn (): SettingsRepository => new class implements SettingsRepository
        {
            public function get(): array
            {
                return [];
            }

            public function put(array $settings): void {}
        });

        $request = Request::create('/?locale=fr');
        $middleware = app(SetAppLocale::class);

        $middleware->handle($request, fn (Request $request) => response()->noContent());

        expect(app()->getLocale())->toBe('fr')
            ->and(Carbon::getLocale())->toBe('fr');
    } finally {
        app()->setLocale($originalAppLocale);
        Carbon::setLocale($originalCarbonLocale);
    }
});
