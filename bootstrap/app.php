<?php

use App\Http\Middleware\AutoLogoutOnInactivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Auto logout inactive users on every web request
        $middleware->web(append: [
            AutoLogoutOnInactivity::class,
        ]);

        // Exclude beacon logout route from CSRF verification
        // (sendBeacon cannot reliably send CSRF tokens)
        $middleware->validateCsrfTokens(except: [
            'auto-logout/beacon',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
