<?php

use App\Exceptions\ApiExceptionConfigurator;
use App\Http\Middleware\AuthTokenMiddleware;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\SupabaseAuth;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        apiPrefix: "",
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
            'supabase.auth' => SupabaseAuth::class,
            'auth.token' => AuthTokenMiddleware::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        ApiExceptionConfigurator::register($exceptions);
    })->create();
