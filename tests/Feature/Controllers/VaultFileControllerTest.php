<?php

declare(strict_types=1);

it('serves attachments with safe headers', function (): void {
    $vault = vaultWithFiles(['attachments/My Photo.png' => 'png-bytes', 'icon.svg' => '<svg onload="alert(1)"/>']);

    $response = $this->get(route('vault-files.show', ['vault' => $vault, 'path' => 'attachments/My Photo.png']));

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin')
        ->assertHeader('ETag');

    expect($response->headers->get('Content-Security-Policy'))->toContain('sandbox')
        ->and($response->headers->get('Cache-Control'))->toContain('private')
        ->and($response->headers->get('Cache-Control'))->toContain('must-revalidate');

    $this->get(route('vault-files.show', ['vault' => $vault, 'path' => 'icon.svg']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml');
});

it('refuses paths outside the vault, missing files and non-attachments', function (string $path): void {
    $vault = vaultWithFiles(['Note.md' => '# secret', '.git/config.png' => 'x']);

    $this->get(route('vault-files.show', ['vault' => $vault, 'path' => $path]))->assertNotFound();
})->with([
    'traversal' => '../../../../etc/passwd',
    'encoded traversal' => '..%2F..%2Fetc%2Fpasswd',
    'markdown' => 'Note.md',
    'hidden folder' => '.git/config.png',
    'missing' => 'nope.png',
]);
