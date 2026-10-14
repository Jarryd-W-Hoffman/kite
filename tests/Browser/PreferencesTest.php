<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('edits preferences in a modal and applies them to the open note', function (): void {
    $vault = vaultWithFiles(['Note.md' => '# Note']);
    resolve(IndexVault::class)->handle($vault);

    $page = visit(route('notes.show', [$vault, Note::query()->sole()]));

    $page->click('@preferences-link')
        ->assertVisible('@preferences-dialog')
        ->assertValue('editor_font', 'mono')
        ->select('editor_font', 'serif')
        ->fill('editor_font_size', '18')
        ->fill('ignored_folders', "node_modules\nArchive")
        ->click('Save preferences')
        ->assertSee('Preferences saved.')
        ->assertMissing('@preferences-dialog')
        ->assertScript("getComputedStyle(document.querySelector('[data-test=note-editor]')).fontSize", '18px')
        ->assertScript("getComputedStyle(document.querySelector('[data-test=note-editor]')).fontFamily.includes('Georgia')", true)
        ->click('@preferences-link')
        ->assertValue('ignored_folders', "node_modules\nArchive")
        ->assertNoJavaScriptErrors();

    expect(config('kite.ignored_folders'))->toBe(['node_modules', 'Archive']);
});

it('opens preferences from the keyboard and closes without saving', function (): void {
    $vault = vaultWithFiles(['Note.md' => '# Note']);
    resolve(IndexVault::class)->handle($vault);

    $page = visit(route('notes.show', [$vault, Note::query()->sole()]));

    $page->keys('@note-editor', 'Control+,')
        ->assertVisible('@preferences-dialog')
        ->fill('daily_folder', 'Unsaved')
        ->click('Cancel')
        ->assertMissing('@preferences-dialog')
        ->assertNoJavaScriptErrors();

    expect(config('kite.daily.folder'))->toBe('Daily');
});
