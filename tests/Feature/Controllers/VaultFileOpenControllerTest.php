<?php

declare(strict_types=1);

use App\Actions\IndexAttachments;
use App\Actions\IndexVault;
use App\Actions\LoadWorkspace;
use App\Models\Vault;
use App\Services\SystemOpener;
use Native\Desktop\Facades\Shell;

it('opens an attachment in its own app, only in the desktop app', function (): void {
    $vault = vaultWithFiles(['attachments/Report.pdf' => 'pdf', 'Note.md' => '# Note']);

    $this->postJson(route('vault-file-opens.store', $vault), ['path' => 'attachments/Report.pdf'])->assertNotFound();

    config(['nativephp-internal.running' => true, 'nativephp-internal.secret' => 'secret']);
    app()->instance(SystemOpener::class, new SystemOpener('Darwin'));
    Shell::fake();

    $this->withHeader('X-NativePHP-Secret', 'secret')
        ->postJson(route('vault-file-opens.store', $vault), ['path' => 'attachments/Report.pdf'])
        ->assertNoContent();

    Shell::assertOpenedFile(osPath($vault->path.'/attachments/Report.pdf'));
});

it('only opens attachments that are in the vault', function (string $path): void {
    $vault = vaultWithFiles(['Note.md' => '# Note', 'attachments/Report.pdf' => 'pdf']);
    config(['nativephp-internal.running' => true, 'nativephp-internal.secret' => 'secret']);
    Shell::fake();

    $this->withHeader('X-NativePHP-Secret', 'secret')
        ->postJson(route('vault-file-opens.store', $vault), ['path' => $path])
        ->assertNotFound();
})->with([
    'a note' => ['Note.md'],
    'a missing file' => ['attachments/Gone.pdf'],
    'outside the vault' => ['../secret.pdf'],
]);

it('lists attachments for the file tree, and moves the tree on when they come and go', function (): void {
    $vault = vaultWithFiles(['Note.md' => '# Note', 'attachments/b.png' => 'png', 'attachments/A.pdf' => 'pdf']);
    resolve(IndexVault::class)->handle($vault);
    $index = resolve(IndexAttachments::class);
    $revision = fn (): int => Vault::query()->findOrFail($vault->id)->currentTreeRevision();

    $before = $revision();
    $index->handle($vault);

    expect($revision())->toBe($before + 1)
        ->and(resolve(LoadWorkspace::class)->handle($vault)['attachments']())->toBe(['attachments/A.pdf', 'attachments/b.png']);

    $before = $revision();
    file_put_contents($vault->path.'/attachments/A.pdf', 'a longer pdf');
    $index->handle($vault);

    expect($revision())->toBe($before);

    unlink($vault->path.'/attachments/b.png');
    $index->handle($vault);

    expect($revision())->toBe($before + 1);
});
