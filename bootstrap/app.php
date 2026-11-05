<?php

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
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('backoffice') || $request->is('backoffice/*')) {
                return route('backoffice.login');
            }

            return route('login');
        });
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'super_admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'affiliate_kader' => \App\Http\Middleware\EnsureAffiliateKader::class,
            'backoffice' => \App\Http\Middleware\EnsureBackoffice::class,
            'numart.api' => \App\Http\Middleware\VerifyNumartApiSecret::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/webhooks/*',
            'api/numart/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
