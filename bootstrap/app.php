<?php

use App\Http\Middleware\ChatbotApiKey;
use App\Http\Middleware\EnsureRole;
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
        $middleware->alias([
            'role' => EnsureRole::class,
            'chatbot.key' => ChatbotApiKey::class,
        ]);
        // Di belakang reverse proxy (Traefik/Easypanel): percayai header X-Forwarded-* agar
        // URL, asset, redirect, dan cookie memakai https & host yang benar.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));
        $middleware->validateCsrfTokens(except: ['midtrans/notification']);
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->isAdmin() ? route('admin.dashboard') : route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
