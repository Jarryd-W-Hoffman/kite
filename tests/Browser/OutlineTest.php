<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('jumps to headings from the outline and heading links', function (): void {
    $vault = vaultWithFiles([
        'Guide.md' => "# Guide\n\nIntro\n\n## Second part\n\nBody",
        'Index.md' => "# Index\n\nRead [[Guide#Second part]].",
    ]);
    resolve(IndexVault::class)->handle($vault);

    $page = visit(route('notes.show', [$vault, Note::query()->where('path', 'Guide.md')->sole()]));

    $page->assertSee('Outline')
        ->click('@outline-second-part')
        ->assertScript('window.location.hash', '#second-part')
        ->assertScript("document.querySelector('.cm-editor').dataset.cursor", '16')
        ->assertNoJavaScriptErrors();

    $page = visit(route('notes.show', [$vault, Note::query()->where('path', 'Index.md')->sole()]));

    $page->click('Guide › Second part')
        ->assertScript('window.location.hash', '#second-part')
        ->assertPresent('#h-second-part')
        ->assertNoJavaScriptErrors();
});
