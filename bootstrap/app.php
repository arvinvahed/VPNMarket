<?php

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
        // ✅ مهم: CSRF exception برای agent
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
            'agent/*',  // ← این خط مهمه!
            'api/*',
        ]);

        $middleware->alias([
            'telegram.webapp' => \App\Http\Middleware\TelegramWebAppAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
