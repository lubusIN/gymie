<?php

namespace App\Providers;

use App\Contracts\SequenceRepository;
use App\Contracts\SettingsRepository;
use App\Contracts\TenantContext;
use App\Models\Attendance;
use App\Models\Enquiry;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Service;
use App\Models\StaffAttendance;
use App\Models\Subscription;
use App\Models\User;
use App\Observers\StaffActivityObserver;
use App\Services\Api\Docs\AddIndexQueryParametersTransformer;
use App\Services\JsonSequenceRepository;
use App\Services\JsonSettingsRepository;
use App\Services\NullTenantContext;
use App\Support\Data;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SettingsRepository::class, JsonSettingsRepository::class);
        $this->app->singleton(SequenceRepository::class, JsonSequenceRepository::class);
        $this->app->singletonIf(TenantContext::class, NullTenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Request $request): void
    {
        if (str_starts_with(Data::string(config('app.url')), 'https://') || $request->isSecure()) {
            URL::forceScheme('https');
        }
        $this->configureRateLimiting();
        $this->configureScrambleApiDocs();
        $this->configureStaffActivityLogging();
    }

    /** Register one safe audit observer for core business records. */
    private function configureStaffActivityLogging(): void
    {
        foreach ([
            Attendance::class,
            Enquiry::class,
            Expense::class,
            Invoice::class,
            InvoiceTransaction::class,
            Member::class,
            Plan::class,
            Service::class,
            StaffAttendance::class,
            Subscription::class,
            User::class,
        ] as $model) {
            $model::observe(StaffActivityObserver::class);
        }
    }

    /**
     * Configure Scramble (OpenAPI) generation for the v1 API.
     *
     * This is guarded so the app remains bootable even when Scramble isn't installed yet.
     */
    private function configureScrambleApiDocs(): void
    {
        if (! class_exists(Scramble::class)) {
            return;
        }

        $config = Scramble::configure();

        $config->routes(static function (Route $route): bool {
            return str_starts_with($route->uri, 'api/v1/');
        });

        $config->withOperationTransformers([
            AddIndexQueryParametersTransformer::class,
        ]);

        if (class_exists(SecurityScheme::class)) {
            $config->withDocumentTransformers(static function (mixed $openApi): void {
                if (! is_object($openApi) || ! method_exists($openApi, 'secure')) {
                    return;
                }

                $openApi->secure(SecurityScheme::http('bearer'));
            });
        }
    }

    /**
     * Configure rate limiters for public and API entry points.
     *
     * Defining these explicitly prevents "throttle:api" from relying on
     * framework defaults that can vary between versions.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(60)->by(Data::string($key));
        });

        RateLimiter::for('api-login', function (Request $request): Limit {
            return Limit::perMinute(10)->by((string) $request->ip());
        });

        RateLimiter::for('attendance-check-in', function (Request $request): Limit {
            return Limit::perMinute(10)->by((string) $request->ip());
        });
    }
}
