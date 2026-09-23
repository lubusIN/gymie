<?php

namespace App\Http\Middleware;

use App\Contracts\SettingsRepository;
use App\Support\AppConfig;
use App\Support\Data;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class SetAppLocale
{
    public function __construct(private readonly SettingsRepository $settingsRepository) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = AppConfig::supportedLocales();
        $fallbackLocale = AppConfig::string('app.fallback_locale', 'en');

        $queryLocale = $request->query('locale');
        $queryLocale = is_string($queryLocale) ? trim($queryLocale) : null;

        $settingsLocale = null;
        try {
            $settings = $this->settingsRepository->get();
            $candidate = data_get($settings, 'general.locale');
            $settingsLocale = is_string($candidate) ? trim($candidate) : null;
        } catch (\Throwable) {
            $settingsLocale = null;
        }

        $headerLocale = $request->getPreferredLanguage($supportedLocales);
        $headerLocale = is_string($headerLocale) ? trim($headerLocale) : null;

        $locale = $queryLocale ?: ($settingsLocale ?: ($headerLocale ?: AppConfig::string('app.locale', 'en')));

        if (! in_array($locale, $supportedLocales, true)) {
            $locale = in_array($fallbackLocale, $supportedLocales, true)
                ? $fallbackLocale
                : Data::string($supportedLocales[0] ?? 'en', 'en');
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
