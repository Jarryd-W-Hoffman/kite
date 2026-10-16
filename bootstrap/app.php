<?php

declare(strict_types=1);

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Services\DesktopCachePaths;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Native\Desktop\Http\Middleware\PreventRegularBrowserAccess;

new DesktopCachePaths()->apply();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Kite routes have no login, so only the desktop window (which sends NativePHP's secret) may reach them.
        $middleware->prepend(PreventRegularBrowserAccess::class);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Note text is saved exactly as typed, and a comment's anchor is matched character for
        // character, surrounding spaces included.
        $middleware->trimStrings(except: ['contents', 'quote', 'prefix', 'suffix']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
