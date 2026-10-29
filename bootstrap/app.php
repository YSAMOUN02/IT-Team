<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'no-store' => \App\Http\Middleware\NoStore::class,
        ]);

        // One paid account must not be shared: limits how many devices a student is signed in on.
        // A student account an admin turned off is signed out on its next request.
        $middleware->web(append: [\App\Http\Middleware\EnsureAccountEnabled::class, \App\Http\Middleware\EnsureKnownDevice::class]);

        // Guests: admin pages -> admin sign-in, everything else -> student sign-in.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin', 'admin/*') ? route('admin.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->isAdmin() ? route('admin.dashboard') : route('account.courses'));

        // PayWay calls this server-to-server; it cannot carry a CSRF token.
        // The statistics beacons only count page views, and often fire after the session has expired.
        $middleware->validateCsrfTokens(except: ['payway/pushback', 'stats/view', 'stats/leave']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
