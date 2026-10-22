<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;
use App\Models\Vault;

function tabsVault(): Vault
{
    $vault = vaultWithFiles([
        'Alpha.md' => '# Alpha',
        'Beta.md' => '# Beta',
        'Gamma.md' => '# Gamma',
    ]);

    resolve(IndexVault::class)->handle($vault);

    return $vault;
}

function noteId(string $path): int
{
    return (int) Note::query()->where('path', $path)->value('id');
}

it('opens notes in new tabs with ctrl click and closes them', function (): void {
    $vault = tabsVault();
    $notes = fn (string $path): string => 'a[href="/vaults/'.$vault->id.'/notes/'.noteId($path).'"]';

    $page = visitSplit(route('notes.show', [$vault, noteId('Alpha.md')]));

    $page->assertMissing('@tab-bar')
        ->withKeyDown('Control', fn ($page) => $page->click('nav '.$notes('Beta.md')))
        ->assertSeeIn('@note-preview', 'Beta')
        ->assertSeeIn('@tab-bar', 'Alpha')
        ->assertSeeIn('@tab-bar', 'Beta')
        ->click('nav '.$notes('Gamma.md'))
        ->assertSeeIn('@note-preview', 'Gamma')
        ->assertDontSeeIn('@tab-bar', 'Beta')
        ->assertSeeIn('@tab-bar', 'Gamma')
        ->click('[aria-label="Close Gamma"]')
        ->assertSeeIn('@note-preview', 'Alpha')
        ->assertMissing('@tab-bar')
        ->assertNoJavaScriptErrors();
});

it('opens a note beside the current one and edits it there', function (): void {
    $vault = tabsVault();

    $page = visitSplit(route('notes.show', [$vault, noteId('Alpha.md')]));

    $page->withKeyDown('Control', fn ($page) => $page->withKeyDown('Shift', fn ($page) => $page->click('nav a[href="/vaults/'.$vault->id.'/notes/'.noteId('Beta.md').'"]')))
        ->assertSeeIn('@split-editor', 'Beta')
        ->assertSeeIn('@note-preview', 'Alpha')
        ->click('@split-editor')
        ->keys('@split-editor', 'End')
        ->typeSlowly('@split-editor', ' edited', 20)
        ->assertSeeIn('@split-pane', 'Saved')
        ->wait(1)
        ->click('@close-split')
        ->assertMissing('@split-pane')
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Beta.md'))->toBe('# Beta edited');
});

it('keeps pinned tabs when opening other notes', function (): void {
    $vault = tabsVault();
    $notes = fn (string $path): string => 'nav[aria-label=Notes] a[href="/vaults/'.$vault->id.'/notes/'.noteId($path).'"]';

    visitSplit(route('notes.show', [$vault, noteId('Alpha.md')]))
        ->withKeyDown('Control', fn ($page) => $page->click($notes('Beta.md')))
        ->click('[aria-label="Pin Beta"]')
        ->assertMissing('[aria-label="Close Beta"]')
        ->click($notes('Gamma.md'))
        ->assertSeeIn('@note-preview', 'Gamma')
        ->assertSeeIn('@tab-bar', 'Beta')
        ->assertSeeIn('@tab-bar', 'Gamma')
        ->click('[role=tab]:first-child a')
        ->assertSeeIn('@note-preview', 'Beta')
        ->keys('@note-editor', 'Control+w')
        ->assertSeeIn('@note-preview', 'Beta')
        ->click('[aria-label="Unpin Beta"]')
        ->assertPresent('[aria-label="Close Beta"]')
        ->assertNoJavaScriptErrors();
});

it('keeps tabs saved before tabs could hold pages', function (): void {
    $vault = tabsVault();
    $alpha = noteId('Alpha.md');
    $beta = noteId('Beta.md');

    $page = visitSplit(route('notes.show', [$vault, $alpha]))->assertSeeIn('@note-preview', 'Alpha');

    $page->script("localStorage.setItem('kite:tabs:{$vault->id}', JSON.stringify({ tabs: [{ id: {$beta}, title: 'Beta', pinned: true }, { id: {$alpha}, title: 'Alpha' }], active: {$alpha} }))");

    $page->refresh()
        ->assertSeeIn('@tab-bar', 'Alpha')
        ->assertSeeIn('@tab-bar', 'Beta')
        ->assertPresent('[aria-label="Unpin Beta"]')
        ->assertAttribute('[role=tab][aria-selected=true] a', 'title', 'Alpha')
        ->assertScript("JSON.parse(localStorage.getItem('kite:tabs:{$vault->id}')).active", "note:{$alpha}")
        // Pest's error check crashes, rather than retrying, if its collector isn't on the new page yet.
        ->assertScript('typeof window.__pestBrowser?.jsErrors', 'object')
        ->assertNoJavaScriptErrors();
});

it('drops tabs whose note has gone, and keeps tab titles current', function (): void {
    $vault = vaultWithFiles(['Alpha.md' => '# Alpha', 'Beta.md' => '# Beta']);
    resolve(IndexVault::class)->handle($vault);
    $alpha = Note::query()->where('path', 'Alpha.md')->value('id');
    $beta = Note::query()->where('path', 'Beta.md')->value('id');

    $page = visit(route('notes.show', [$vault, $alpha]));
    $page->script("localStorage.setItem('kite:tabs:{$vault->id}', JSON.stringify({ tabs: [{ kind: 'note', id: {$alpha}, title: 'Alpha' }, { kind: 'note', id: 999, title: 'Gone' }, { kind: 'note', id: {$beta}, title: 'Old name' }], active: 'note:{$alpha}' }))");

    $page->refresh()
        ->assertSeeIn('@tab-bar', 'Beta')
        ->assertDontSeeIn('@tab-bar', 'Gone')
        ->assertDontSeeIn('@tab-bar', 'Old name')
        ->assertNoJavaScriptErrors();
});
