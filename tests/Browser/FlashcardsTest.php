<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('studies a deck, bringing back cards marked again, and saves the schedule in the vault', function (): void {
    $vault = vaultWithFiles([
        'Spanish.md' => "# Spanish #flashcards/spanish\n\nperro :: dog\ngato :: cat",
        'Home.md' => '# Home',
    ]);
    resolve(IndexVault::class)->handle($vault);

    $page = visit(route('notes.show', [$vault, Note::query()->where('path', 'Home.md')->sole()]))
        ->click('@flashcards-link')
        ->assertSeeIn('@flashcards-summary', '2 cards to review today')
        ->assertSeeIn('@decks', 'spanish')
        ->click('@study-deck')
        ->assertSeeIn('@session-remaining', '2 left')
        ->assertSeeIn('@flashcard-front', 'perro')
        ->assertMissing('@flashcard-back')
        ->keys('@flashcard', 'Space')
        ->assertSeeIn('@flashcard-back', 'dog')
        ->assertSeeIn('@grade-good', '1 day')
        ->click('@grade-again')
        ->assertSeeIn('@session-remaining', '2 left')
        ->assertSeeIn('@flashcard-front', 'gato')
        ->click('@show-answer')
        ->keys('@flashcard', '3')
        ->assertSeeIn('@session-remaining', '1 left')
        ->assertSeeIn('@flashcard-front', 'perro')
        ->keys('@flashcard', 'Space')
        ->keys('@flashcard', '4')
        ->assertSee('All caught up')
        ->assertSee('You reviewed 3 cards.')
        ->assertNoJavaScriptErrors();

    $schedule = json_decode((string) file_get_contents($vault->path.'/.kite/flashcards.json'), true)['cards'];

    expect($schedule)->toHaveCount(2)
        ->and(collect($schedule)->pluck('interval')->sort()->values()->all())->toBe([1, 4]);

    $page->click('@flashcards-back')
        ->assertSeeIn('@flashcards-summary', 'Nothing to review today.');
});

it('explains how to write cards when there are none', function (): void {
    $vault = vaultWithFiles(['Home.md' => '# Home']);
    resolve(IndexVault::class)->handle($vault);

    visit(route('notes.show', [$vault, Note::query()->sole()]))
        ->click('@flashcards-link')
        ->assertSee('No flashcards yet')
        ->assertSeeIn('@flashcards-how-to', 'perro ::: dog')
        ->assertNoJavaScriptErrors();
});
