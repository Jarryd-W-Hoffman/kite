<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('renders the vault graph and opens notes from the sidebar', function (): void {
    $vault = vaultWithFiles([
        'Home.md' => '# Home [[Topic]] [[Missing]]',
        'Topic.md' => '# Topic [[Home]]',
        'Alone.md' => '# Alone',
    ]);
    resolve(IndexVault::class)->handle($vault);

    $page = visitSplit(route('notes.show', [$vault, Note::query()->where('path', 'Home.md')->sole()]));

    $page->assertSee('Local graph')
        ->click('@graph-link')
        ->assertSee('3 notes · 2 links')
        ->assertAttribute('@graph-unresolved', 'title', "Show links to notes that don't exist yet, as hollow dots")
        ->assertAttribute('@graph-orphans', 'title', 'Show notes with no links in or out')
        ->assertAriaAttribute('@graph-canvas', 'label', 'Graph of 4 notes and 2 links')
        ->click('Orphans')
        ->assertAriaAttribute('@graph-canvas', 'label', 'Graph of 3 notes and 2 links')
        ->click('Unresolved')
        ->assertAriaAttribute('@graph-canvas', 'label', 'Graph of 2 notes and 1 links')
        ->assertNoJavaScriptErrors();
});
