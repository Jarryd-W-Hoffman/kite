<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('navigates from inline tags to tag pages and filters the graph', function (): void {
    $vault = vaultWithFiles([
        'Kite.md' => "# Kite\nPart of #project/kite",
        'Garden.md' => "# Garden\n#project",
        'Other.md' => '# Other #misc',
    ]);
    resolve(IndexVault::class)->handle($vault);

    $page = visitSplit(route('notes.show', [$vault, Note::query()->where('path', 'Kite.md')->sole()]));

    $page->click('a.tag-link[data-tag="project/kite"]')
        ->assertSee('1 note')
        ->assertSeeIn('@tagged-notes', 'Kite.md')
        ->click('@tag-project')
        ->assertSee('2 notes')
        ->assertSeeIn('@tagged-notes', 'Garden.md')
        ->assertDontSeeIn('@tagged-notes', 'Other.md')
        ->click('@graph-link')
        ->select('@graph-colour', 'tag')
        ->select('@graph-tag', 'project')
        ->assertPresent('@graph-canvas')
        ->assertNoJavaScriptErrors();
});
