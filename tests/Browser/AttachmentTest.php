<?php

declare(strict_types=1);

use App\Actions\IndexAttachments;
use App\Actions\IndexVault;
use App\Models\Note;

it('renders embedded images from the vault', function (): void {
    $vault = vaultWithFiles([
        'Note.md' => "# Photos\n\n![[pixel.png]]",
        'attachments/pixel.png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='),
    ]);
    resolve(IndexVault::class)->handle($vault);
    resolve(IndexAttachments::class)->handle($vault);

    $page = visitSplit(route('notes.show', [$vault, Note::query()->sole()]));

    $page->assertPresent('img.attachment-embed')
        ->assertScript("document.querySelector('img.attachment-embed').complete && document.querySelector('img.attachment-embed').naturalWidth === 1", true)
        ->assertNoJavaScriptErrors();
});
