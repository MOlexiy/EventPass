<?php

use App\Http\Middleware\EnsureUserIsOrganizer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum: requests from the SPA domain get session + CSRF protection.
        $middleware->statefulApi();
        $middleware->alias(['organizer' => EnsureUserIsOrganizer::class]);
        // Guests hitting auth-only routes get 401 JSON, not a redirect.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->expectsJson() ? null : config('payments.frontend_url').'/login');
        $middleware->redirectUsersTo(fn () => config('payments.frontend_url').'/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
