<?php

declare(strict_types=1);

use App\Http\Middleware\ContentSecurityPolicy;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Vault;
use App\Services\DesktopCachePaths;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Native\Desktop\Http\Middleware\PreventRegularBrowserAccess;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

        // A key binding of '' means no shortcut, which null (the default) must not be mistaken for.
        $middleware->convertEmptyStringsToNull(except: [
            fn (Request $request): bool => $request->is('key-bindings'),
        ]);

        $middleware->web(append: [
            ContentSecurityPolicy::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sent only when the user has turned crash reports on; see App\Services\CrashReports.
        Integration::handles($exceptions);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );

        // Visiting a page that's gone (say, a note deleted while a tab or link to it stayed around)
        // leads back to its vault with a word about why, rather than to a bare error page. Other
        // requests, such as for an attachment, still get a plain 404.
        $exceptions->render(function (NotFoundHttpException $exception, Request $request): ?RedirectResponse {
            $vault = $request->route()?->parameter('vault');

            if (! $request->isMethod('GET') || ! $request->hasHeader('X-Inertia') || $vault === null) {
                return null;
            }

            $vault = $vault instanceof Vault ? $vault : Vault::query()->find($vault);

            if (! $vault instanceof Vault) {
                return to_route('vaults.index');
            }

            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('That page isn’t there any more. Its note may have been deleted, or moved or renamed outside Kite.'),
            ]);

            return to_route('vaults.show', $vault);
        });
    })->create();
