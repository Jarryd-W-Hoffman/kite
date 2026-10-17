<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

it('installs a downloaded update only in the desktop app', function (): void {
    Http::fake();

    $this->post(route('app-update.store'))->assertNotFound();

    config(['nativephp-internal.running' => true, 'nativephp-internal.secret' => 'secret']);

    $this->withHeader('X-NativePHP-Secret', 'secret')->post(route('app-update.store'))->assertNoContent();

    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), 'auto-updater/quit-and-install'));
    Http::assertSentCount(1);
});

it('treats the app quitting before it answers as success', function (): void {
    Http::fake(fn () => throw new ConnectionException('cURL error 52: Empty reply from server'));
    config(['nativephp-internal.running' => true, 'nativephp-internal.secret' => 'secret']);

    $this->withHeader('X-NativePHP-Secret', 'secret')->post(route('app-update.store'))->assertNoContent();
});

it('still fails when installing goes wrong for another reason', function (): void {
    Http::fake(fn () => throw new RuntimeException('Something else broke'));
    config(['nativephp-internal.running' => true, 'nativephp-internal.secret' => 'secret']);

    $this->withoutExceptionHandling();

    expect(fn () => $this->withHeader('X-NativePHP-Secret', 'secret')->post(route('app-update.store')))
        ->toThrow(RuntimeException::class, 'Something else broke');
});
