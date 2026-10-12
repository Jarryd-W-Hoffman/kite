<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('opens a folder as a vault and shows its home note', function (): void {
    $folder = vaultWithFiles([
        'Home.md' => "# Home\nStart at [[Projects/Kite]].",
        'Projects/Kite.md' => "# Kite\nBack [[Home]]",
    ]);
    $folder->delete();

    $page = visit(route('vaults.index'));

    $page->fill('path', $folder->path)
        ->press('Open')
        ->assertSeeIn('@note-preview', 'Start at')
        ->assertSee('Projects')
        ->assertSee('Backlinks')
        ->assertSee('Kite')
        ->assertNoJavaScriptErrors();
});

it('autosaves edits to disk and renders the preview', function (): void {
    $vault = vaultWithFiles(['Note.md' => '# Draft']);
    resolve(IndexVault::class)->handle($vault);

    $page = visit(route('notes.show', [$vault, Note::query()->sole()]));

    $page->fill('@note-editor', "# Final\n\nHello **bold world**")
        ->assertSeeIn('@note-preview', 'bold world')
        ->assertSee('Saved')
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Note.md'))->toBe("# Final\n\nHello **bold world**");
});

it('follows wiki links and creates notes from unresolved ones', function (): void {
    $vault = vaultWithFiles([
        'A.md' => "# Alpha\nGo to [[B]] or [[Brand New]].",
        'B.md' => '# Beta page',
    ]);
    resolve(IndexVault::class)->handle($vault);

    $page = visit(route('notes.show', [$vault, Note::query()->where('path', 'A.md')->sole()]));

    $page->click('B')
        ->assertDontSeeIn('@note-preview', 'Go to')
        ->assertSeeIn('@note-preview', 'Beta page')
        ->assertSee('Alpha')
        ->navigate(route('notes.show', [$vault, Note::query()->where('path', 'A.md')->sole()]))
        ->click('Brand New')
        ->assertDontSeeIn('@note-preview', 'Go to')
        ->assertSeeIn('@note-preview', 'Brand New')
        ->assertNoJavaScriptErrors();

    expect(file_exists($vault->path.'/Brand New.md'))->toBeTrue();
});

it('searches notes from the command palette', function (): void {
    $vault = vaultWithFiles([
        'Home.md' => '# Home',
        'Garden.md' => "# Garden\nTomatoes and basil.",
    ]);
    resolve(IndexVault::class)->handle($vault);

    $page = visit(route('notes.show', [$vault, Note::query()->where('path', 'Home.md')->sole()]));

    $page->keys('@note-editor', 'Control+k')
        ->fill('@command-palette-input', 'basil')
        ->assertSee('Garden.md')
        ->keys('@command-palette-input', 'Enter')
        ->assertSeeIn('@note-preview', 'Tomatoes and basil.')
        ->assertNoJavaScriptErrors();
});

it('suggests notes while typing a wiki link', function (): void {
    $vault = vaultWithFiles([
        'Home.md' => '',
        'Projects/Roadmap.md' => '# Roadmap',
    ]);
    resolve(IndexVault::class)->handle($vault);

    $page = visit(route('notes.show', [$vault, Note::query()->where('path', 'Home.md')->sole()]));

    $page->fill('@note-editor', 'See [[Road')
        ->assertSee('Roadmap')
        ->keys('@note-editor', 'Enter')
        ->assertValue('@note-editor', 'See [[Roadmap]]')
        ->assertNoJavaScriptErrors();
});
