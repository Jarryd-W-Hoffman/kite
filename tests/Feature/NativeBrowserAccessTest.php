<?php

declare(strict_types=1);

beforeEach(function (): void {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.secret' => 'desktop-secret',
    ]);
});

it('blocks regular browsers from the desktop app server', function (): void {
    $this->get(route('vaults.index'))->assertForbidden();
    $this->getJson(route('vault-search.index', ['vault' => 1]))->assertForbidden();
});

it('allows the desktop window through with the native secret', function (): void {
    $this->withHeader('X-NativePHP-Secret', 'desktop-secret')
        ->get(route('vaults.index'))
        ->assertOk();

    $this->withUnencryptedCookie('_php_native', 'desktop-secret')
        ->get(route('vaults.index'))
        ->assertOk();
});

it('rejects the wrong secret', function (): void {
    $this->withHeader('X-NativePHP-Secret', 'guess')
        ->get(route('vaults.index'))
        ->assertForbidden();
});

it('does not restrict access outside the desktop app', function (): void {
    config(['nativephp-internal.running' => false]);

    $this->get(route('vaults.index'))->assertOk();
});
