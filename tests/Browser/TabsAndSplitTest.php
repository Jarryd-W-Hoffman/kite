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

    $page = visit(route('notes.show', [$vault, noteId('Alpha.md')]));

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

    $page = visit(route('notes.show', [$vault, noteId('Alpha.md')]));

    $page->withKeyDown('Control', fn ($page) => $page->withKeyDown('Shift', fn ($page) => $page->click('nav a[href="/vaults/'.$vault->id.'/notes/'.noteId('Beta.md').'"]')))
        ->assertSeeIn('@split-editor', '# Beta')
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
