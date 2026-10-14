<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('lists keyboard shortcuts for the current platform', function (): void {
    $vault = vaultWithFiles(['Note.md' => '# Note']);
    resolve(IndexVault::class)->handle($vault);

    $page = visit(route('notes.show', [$vault, Note::query()->sole()]));

    $page->assertSee('Ctrl+K')
        ->assertDontSee('⌘K')
        ->click('@shortcuts-link')
        ->assertVisible('@shortcuts-dialog')
        ->assertSeeIn('@shortcuts-dialog', 'Toggle dark mode')
        ->assertSeeIn('@shortcuts-dialog', 'Ctrl+Shift+L')
        ->keys('@shortcuts-dialog', 'Escape')
        ->assertMissing('@shortcuts-dialog')
        ->keys('@note-editor', 'Control+/')
        ->assertVisible('@shortcuts-dialog')
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Note.md'))->toBe('# Note');
});
