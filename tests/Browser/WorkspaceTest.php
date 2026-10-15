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

    $page = visitSplit(route('vaults.index'));

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

    $page = visitSplit(route('notes.show', [$vault, Note::query()->sole()]));

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

    $page = visitSplit(route('notes.show', [$vault, Note::query()->where('path', 'A.md')->sole()]));

    $page->click('a.wiki-link[data-wiki-target="B"]')
        ->assertDontSeeIn('@note-preview', 'Go to')
        ->assertSeeIn('@note-preview', 'Beta page')
        ->assertSee('Alpha')
        ->navigate(route('notes.show', [$vault, Note::query()->where('path', 'A.md')->sole()]))
        ->click('a.wiki-link[data-wiki-target="Brand New"]')
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

    $page = visitSplit(route('notes.show', [$vault, Note::query()->where('path', 'Home.md')->sole()]));

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

    $page = visitSplit(route('notes.show', [$vault, Note::query()->where('path', 'Home.md')->sole()]));

    $page->click('@note-editor')
        ->typeSlowly('@note-editor', 'See [[Road', 20)
        ->assertSeeIn('.cm-tooltip-autocomplete', 'Roadmap')
        ->assertSeeIn('.cm-tooltip-autocomplete', 'Projects')
        ->wait(0.2)
        ->keys('@note-editor', 'Enter')
        ->assertSeeIn('@note-editor', 'See [[Roadmap]]')
        ->assertScript("document.querySelector('[data-test=note-editor]').textContent", 'See [[Roadmap]]')
        ->assertNoJavaScriptErrors();
});

it('follows wiki links from the editor with cmd or ctrl click', function (): void {
    $vault = vaultWithFiles([
        'Home.md' => 'Go to [[Projects/Roadmap|the roadmap]] and [[Ideas]].',
        'Projects/Roadmap.md' => '# Roadmap',
    ]);
    resolve(IndexVault::class)->handle($vault);

    $page = visitSplit(route('notes.show', [$vault, Note::query()->where('path', 'Home.md')->sole()]));

    $page->withKeyDown('Control', fn ($page) => $page->click('.cm-wikilink[data-wiki-target="Projects/Roadmap"]'))
        ->assertSeeIn('@note-preview', 'Roadmap')
        ->assertPathIs('/vaults/'.$vault->id.'/notes/'.Note::query()->where('path', 'Projects/Roadmap.md')->value('id'))
        ->assertNoJavaScriptErrors();
});

it('keeps undo history across autosaves', function (): void {
    $vault = vaultWithFiles(['Note.md' => 'First']);
    resolve(IndexVault::class)->handle($vault);

    $page = visitSplit(route('notes.show', [$vault, Note::query()->sole()]));

    $page->click('@note-editor')
        ->keys('@note-editor', 'End')
        ->typeSlowly('@note-editor', ' second', 20)
        ->assertSee('Saved')
        ->assertSeeIn('@note-preview', 'First second')
        ->keys('@note-editor', 'Control+z')
        ->assertSeeIn('@note-editor', 'First')
        ->assertDontSeeIn('@note-editor', 'second')
        ->assertNoJavaScriptErrors();
});

it('deletes a note after confirming in a dialog', function (): void {
    $vault = vaultWithFiles(['Keep.md' => '# Keep', 'Gone.md' => '# Gone']);
    resolve(IndexVault::class)->handle($vault);

    visitSplit(route('notes.show', [$vault, Note::query()->where('path', 'Gone.md')->sole()]))
        ->click('@note-actions')
        ->click('@delete-note')
        ->assertSeeIn('@delete-note-dialog', 'Delete “Gone”?')
        ->assertSeeIn('@delete-note-dialog', 'will be permanently deleted')
        ->click('Delete note')
        ->assertSee('Deleted “Gone”.')
        ->assertNoJavaScriptErrors();

    expect(Note::query()->pluck('path')->all())->toBe(['Keep.md']);
});

it('shows frontmatter in the editor without heading styles', function (): void {
    $vault = vaultWithFiles(['Note.md' => "---\nstatus: active\n---\n# Note\n\nTitle\n---"]);
    resolve(IndexVault::class)->handle($vault);

    $weight = fn (string $text): string => "getComputedStyle([...document.querySelectorAll('.cm-line')].find((line) => line.textContent === '{$text}').firstElementChild).fontWeight";

    visitSplit(route('notes.show', [$vault, Note::query()->sole()]))
        ->click('@properties-chip')
        ->assertScript($weight('status: active'), '400')
        ->assertScript("Number({$weight('Title')}) >= 600", true)
        ->assertNoJavaScriptErrors();
});
