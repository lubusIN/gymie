<?php

use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SetAppLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\Exceptions\InvalidFilterQuery;
use Spatie\QueryBuilder\Exceptions\InvalidIncludeQuery;
use Spatie\QueryBuilder\Exceptions\InvalidQuery;
use Spatie\QueryBuilder\Exceptions\InvalidSortQuery;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(prepend: [
            SetAppLocale::class,
        ]);

        $middleware->api(prepend: [
            SetAppLocale::class,
            ForceJsonResponse::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReportDuplicates();
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $exception): bool => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (InvalidQuery $exception, Request $request) {
            $errors = ['query' => [$exception->getMessage()]];

            if ($exception instanceof InvalidFilterQuery) {
                $errors = ['filter' => [$exception->getMessage()]];
            } elseif ($exception instanceof InvalidIncludeQuery) {
                $errors = ['include' => [$exception->getMessage()]];
            } elseif ($exception instanceof InvalidSortQuery) {
                $errors = ['sort' => [$exception->getMessage()]];
            }

            return response()->json([
                'message' => __('app.api.invalid_query'),
                'errors' => $errors,
            ], 400);
        });
    })->create();
