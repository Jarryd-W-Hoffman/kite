<?php

declare(strict_types=1);

use Native\Desktop\Facades\Shell;

it('opens web links in the system browser only in the desktop app', function (): void {
    $this->postJson(route('external-links.store'), ['url' => 'https://example.com'])->assertNotFound();

    config(['nativephp-internal.running' => true, 'nativephp-internal.secret' => 'secret']);
    Shell::fake();

    $this->withHeader('X-NativePHP-Secret', 'secret')
        ->postJson(route('external-links.store'), ['url' => 'https://example.com/page?q=1'])
        ->assertNoContent();

    Shell::assertOpenedExternal('https://example.com/page?q=1');
});

it('only opens http and https links', function (string $url): void {
    config(['nativephp-internal.running' => true, 'nativephp-internal.secret' => 'secret']);
    Shell::fake();

    $this->withHeader('X-NativePHP-Secret', 'secret')
        ->postJson(route('external-links.store'), ['url' => $url])
        ->assertJsonValidationErrors('url');
})->with(['file:///etc/passwd', 'javascript:alert(1)', 'ftp://example.com/file', 'not a url', '']);
