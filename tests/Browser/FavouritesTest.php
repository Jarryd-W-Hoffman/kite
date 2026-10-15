<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('stars notes into favourites and lists recent ones', function (): void {
    $vault = vaultWithFiles(['Alpha.md' => '# Alpha', 'Beta.md' => '# Beta']);
    resolve(IndexVault::class)->handle($vault);
    $id = fn (string $path): int => (int) Note::query()->where('path', $path)->value('id');

    visitSplit(route('notes.show', [$vault, $id('Alpha.md')]))
        ->assertMissing('@favourites')
        ->click('@star-note')
        ->assertAttribute('@star-note', 'aria-pressed', 'true')
        ->assertSeeIn('@favourites', 'Alpha')
        ->click('nav[aria-label=Notes] a[href="/vaults/'.$vault->id.'/notes/'.$id('Beta.md').'"]')
        ->assertSeeIn('@recent-notes', 'Beta')
        ->assertSeeIn('@recent-notes', 'Alpha')
        ->click('@favourite-link')
        ->assertSeeIn('@note-preview', 'Alpha')
        ->click('@star-note')
        ->assertMissing('@favourites')
        ->assertNoJavaScriptErrors();
});
