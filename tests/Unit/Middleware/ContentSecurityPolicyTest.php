<?php

declare(strict_types=1);

use App\Http\Middleware\ContentSecurityPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;

it('lets only scripts from Kite, or with the page nonce, run', function (): void {
    Vite::useHotFile(storage_path('framework/testing/no-hot'));

    $response = new ContentSecurityPolicy()->handle(Request::create('/'), fn (): Response => response('OK'));
    $policy = (string) $response->headers->get('Content-Security-Policy');

    expect(Vite::cspNonce())->toBeString()
        ->and($policy)->toContain("script-src 'self' 'nonce-".Vite::cspNonce()."';")
        ->and($policy)->toContain("style-src 'self' 'unsafe-inline';")
        ->and($policy)->toContain("img-src 'self' data: blob: https: http:;")
        ->and($policy)->toContain("connect-src 'self';")
        ->and($policy)->toContain("object-src 'none';")
        ->and($policy)->toEndWith("form-action 'self'")
        ->and($policy)->not->toContain('unsafe-eval');
});

it('allows the Vite dev server while developing', function (): void {
    $hot = storage_path('framework/testing/hot');
    File::ensureDirectoryExists(dirname($hot));
    File::put($hot, "http://[::1]:5173\n");
    Vite::useHotFile($hot);

    $response = new ContentSecurityPolicy()->handle(Request::create('/'), fn (): Response => response('OK'));
    $policy = (string) $response->headers->get('Content-Security-Policy');

    File::delete($hot);

    expect($policy)->toContain("script-src 'self' 'nonce-".Vite::cspNonce()."' http://[::1]:5173;")
        ->and($policy)->toContain("connect-src 'self' http://[::1]:5173 ws://[::1]:5173;");
});

it('keeps a policy the response set for itself', function (): void {
    $response = new ContentSecurityPolicy()->handle(
        Request::create('/'),
        fn (): Response => response('OK', 200, ['Content-Security-Policy' => "default-src 'none'"]),
    );

    expect($response->headers->get('Content-Security-Policy'))->toBe("default-src 'none'");
});

it('sends the policy with pages, and the nonce on the inline script', function (): void {
    $response = $this->get(route('vaults.index'))->assertOk();
    $nonce = Vite::cspNonce();

    expect($response->headers->get('Content-Security-Policy'))->toContain("'nonce-{$nonce}'")
        ->and($response->getContent())->toContain("<script nonce=\"{$nonce}\">");
});
