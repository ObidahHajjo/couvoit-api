<?php

use App\Exceptions\ApiExceptionConfigurator;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\LocalJwtAuth;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: '',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['jwt']]
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
            'jwt' => LocalJwtAuth::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        ApiExceptionConfigurator::register($exceptions);
    })->create();
