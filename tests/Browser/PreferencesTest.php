<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('saves preferences and applies them to the editor', function (): void {
    $vault = vaultWithFiles(['Note.md' => '# Note']);
    resolve(IndexVault::class)->handle($vault);

    $page = visit(route('notes.show', [$vault, Note::query()->sole()]));

    $page->click('@preferences-link')
        ->assertSee('Preferences')
        ->select('editor_font', 'serif')
        ->fill('editor_font_size', '18')
        ->fill('ignored_folders', "node_modules\nArchive")
        ->click('Save preferences')
        ->assertSee('Preferences saved.')
        ->assertValue('ignored_folders', "node_modules\nArchive")
        ->click('Back to notes')
        ->assertScript("getComputedStyle(document.querySelector('[data-test=note-editor]')).fontSize", '18px')
        ->assertScript("document.querySelector('[data-test=note-editor]').classList.contains('font-serif')", true)
        ->assertNoJavaScriptErrors();

    expect(config('kite.ignored_folders'))->toBe(['node_modules', 'Archive']);
});
