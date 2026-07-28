<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // /oauth/* routes live in routes/oauth.php, required from routes/web.php,
        // so they inherit the `web` group (session, CSRF) even though most of
        // them are hit by external, session-less OAuth/OIDC clients rather than
        // browsers with a CSRF token.
        $middleware->validateCsrfTokens(except: [
            'oauth/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
