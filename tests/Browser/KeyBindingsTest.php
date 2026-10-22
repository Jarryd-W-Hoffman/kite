<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('gives a command another shortcut, which then runs it', function (): void {
    $vault = vaultWithFiles(['Home.md' => '# Home']);
    resolve(IndexVault::class)->handle($vault);

    $page = visit(route('notes.show', [$vault, Note::query()->sole()]))
        ->click('@shortcuts-link')
        ->assertSeeIn('@shortcuts-dialog', 'Search notes')
        ->click('@customise-shortcuts')
        ->click('@record-shortcut-search')
        ->assertSeeIn('@record-shortcut-search', 'Press keys…')
        ->keys('@record-shortcut-search', ['Shift+p'])
        ->assertSeeIn('@shortcut-search', 'Hold Ctrl (and Alt or Shift if you like)')
        ->keys('@record-shortcut-search', ['Control+b'])
        ->assertSeeIn('@shortcut-search', 'Already used for Bold.')
        ->assertSeeIn('@record-shortcut-search', 'Ctrl+K')
        ->click('@record-shortcut-search')
        ->keys('@record-shortcut-search', ['Control+Shift+p'])
        ->assertSeeIn('@record-shortcut-search', 'Ctrl+Shift+P')
        ->assertDontSeeIn('@shortcut-search', 'Already used')
        ->click('@remove-shortcut-graph')
        ->assertSeeIn('@record-shortcut-graph', 'None')
        ->click('@done-customising-shortcuts')
        ->assertSeeIn('@shortcuts-dialog', 'Ctrl+Shift+P')
        ->keys('@shortcuts-dialog', 'Escape')
        ->assertMissing('@shortcuts-dialog');

    $page->refresh()
        ->keys('@note-editor', ['Control+Shift+p'])
        ->assertVisible('@command-palette-input')
        ->assertNoJavaScriptErrors();
});

it('puts every shortcut back as it was', function (): void {
    $vault = vaultWithFiles(['Home.md' => '# Home']);
    resolve(IndexVault::class)->handle($vault);

    visit(route('notes.show', [$vault, Note::query()->sole()]))
        ->click('@shortcuts-link')
        ->click('@customise-shortcuts')
        ->click('@record-shortcut-new-note')
        ->keys('@record-shortcut-new-note', ['Control+Alt+n'])
        ->assertSeeIn('@record-shortcut-new-note', 'Ctrl+Alt+N')
        ->click('@record-shortcut-graph')
        ->keys('@record-shortcut-graph', ['Control+k'])
        ->assertSeeIn('@shortcut-graph', 'Already used for Search.')
        ->click('@record-shortcut-graph')
        ->keys('@record-shortcut-graph', 'Escape')
        ->assertSeeIn('@record-shortcut-graph', 'Ctrl+G')
        ->assertVisible('@shortcuts-dialog')
        ->click('@reset-all-shortcuts')
        ->assertSeeIn('@record-shortcut-new-note', 'Ctrl+N')
        ->assertNoJavaScriptErrors();
});
