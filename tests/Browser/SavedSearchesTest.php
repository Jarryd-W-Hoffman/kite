<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;
use App\Models\SavedSearch;

it('saves a search from the palette and keeps it up to date in the sidebar', function (): void {
    $vault = vaultWithFiles([
        'Kite.md' => "# Kite\n\n#project Flying in the wind",
        'Garden.md' => "# Garden\n\n#project Wind breaks",
        'Diary.md' => "# Diary\n\nA calm day",
    ]);
    resolve(IndexVault::class)->handle($vault);

    $page = visit(route('notes.show', [$vault, Note::query()->where('path', 'Diary.md')->sole()]))
        ->keys('@note-editor', 'Control+k')
        ->type('@command-palette-input', 'tag:project wind')
        ->click('@palette-save-search')
        ->assertValue('@search-name', 'tag:project wind')
        ->assertValue('@search-query', 'tag:project wind')
        ->assertSeeIn('@search-count', '2 notes')
        ->assertSeeIn('@search-results', 'Kite.md')
        ->assertSeeIn('@search-results', 'Garden.md')
        ->assertSeeIn('@search-link', 'tag:project wind');

    $page->clear('@search-name')
        ->type('@search-name', 'Windy projects')
        ->clear('@search-query')
        ->type('@search-query', 'tag:project flying')
        ->assertSeeIn('@search-count', '1 note')
        ->assertDontSeeIn('@search-results', 'Garden')
        ->assertSeeIn('@search-link', 'Windy projects')
        ->assertNoJavaScriptErrors();

    expect(SavedSearch::query()->sole()->only(['name', 'query']))->toBe(['name' => 'Windy projects', 'query' => 'tag:project flying']);

    $page->click('@delete-search')
        ->assertSee('Deleted the search “Windy projects”.')
        ->assertMissing('@search-link');
});
