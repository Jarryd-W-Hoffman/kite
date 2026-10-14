<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

it('returns no folder outside the desktop app', function (): void {
    $this->postJson(route('vault-folder.store'))
        ->assertOk()
        ->assertExactJson(['path' => null]);
});

it('opens the native folder picker in the desktop app', function (?array $result, ?string $expected): void {
    config(['nativephp-internal.running' => true]);

    Http::fake(['*dialog/open' => Http::response(['result' => $result])]);

    $this->withoutMiddleware()
        ->postJson(route('vault-folder.store'))
        ->assertOk()
        ->assertExactJson(['path' => $expected]);

    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), 'dialog/open')
        && in_array('openDirectory', $request['properties'], true)
        && $request['title'] === 'Open a folder as a vault');
})->with([
    'picked' => [['/Users/me/Notes'], '/Users/me/Notes'],
    'cancelled' => [null, null],
]);
