<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('runs commands from the palette', function (): void {
    $vault = vaultWithFiles(['Home.md' => '# Home']);
    resolve(IndexVault::class)->handle($vault);

    visit(route('notes.show', [$vault, Note::query()->sole()]))
        ->keys('@note-editor', 'Control+k')
        ->typeSlowly('@command-palette-input', '>dark')
        ->assertSeeIn('@palette-command', 'Toggle dark mode')
        ->keys('@command-palette-input', 'Enter')
        ->assertScript("document.documentElement.classList.contains('dark')", true)
        ->keys('@note-editor', 'Control+k')
        ->typeSlowly('@command-palette-input', '>history')
        ->keys('@command-palette-input', 'Enter')
        ->assertVisible('@version-history')
        ->keys('@version-history', 'Escape')
        ->assertMissing('@version-history')
        ->keys('@note-editor', 'Control+k')
        ->typeSlowly('@command-palette-input', '>open tasks')
        ->keys('@command-palette-input', 'Enter')
        ->assertPathEndsWith('/tasks')
        ->assertNoJavaScriptErrors();
});

it('only offers note commands while a note is open', function (): void {
    $vault = vaultWithFiles(['Home.md' => '# Home']);
    resolve(IndexVault::class)->handle($vault);

    visit(route('tasks.index', $vault))
        ->click('nav[aria-label=Workspace] button:first-child')
        ->typeSlowly('@command-palette-input', '>export')
        ->assertSee('No commands match.')
        ->assertNoJavaScriptErrors();
});

it('filters search results by tag, folder and property', function (): void {
    $vault = vaultWithFiles([
        'Work/Standup.md' => "---\nstatus: active\n---\n# Standup #work\n\nsync",
        'Home/Groceries.md' => "# Groceries\n\nsync",
    ]);
    resolve(IndexVault::class)->handle($vault);

    visit(route('notes.show', [$vault, Note::query()->where('path', 'Home/Groceries.md')->sole()]))
        ->keys('@note-editor', 'Control+k')
        ->typeSlowly('@command-palette-input', 'sync tag:work')
        ->assertSeeIn('@palette-filters', 'tag:work')
        ->assertSeeIn('#kite-palette-item-0', 'Work/Standup.md')
        ->assertSeeIn('#kite-palette-item-2', 'Save search “sync tag:work”')
        ->assertMissing('#kite-palette-item-3')
        ->assertDontSeeIn('#kite-palette-results', 'Home/Groceries.md')
        ->assertNoJavaScriptErrors();
});
