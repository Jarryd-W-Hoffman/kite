<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('hides everything but the note in focus mode', function (): void {
    $vault = vaultWithFiles(['Draft.md' => "---\nword_goal: 10\n---\n# Draft\n\nOne two three four"]);
    resolve(IndexVault::class)->handle($vault);

    visitSplit(route('notes.show', [$vault, Note::query()->sole()]))
        ->assertPresent('nav[aria-label=Workspace]')
        ->keys('@note-editor', 'Control+Shift+F')
        ->assertMissing('nav[aria-label=Workspace]')
        ->assertMissing('@note-preview')
        ->assertAttribute('[data-typewriter]', 'data-typewriter', 'true')
        ->assertSeeIn('@focus-words', '5 / 10 words')
        ->assertAttribute('[role=progressbar]', 'aria-valuenow', '5')
        ->keys('@note-editor', 'Control+End')
        ->typeSlowly('@note-editor', ' five six seven eight nine ten')
        ->assertSeeIn('@focus-goal', 'Goal reached')
        ->keys('@note-editor', 'Escape')
        ->assertMissing('@focus-bar')
        ->assertPresent('nav[aria-label=Workspace]')
        ->assertVisible('@note-preview')
        ->assertMissing('[data-typewriter]')
        ->assertNoJavaScriptErrors();
});

it('sets a word goal in the note properties', function (): void {
    $vault = vaultWithFiles(['Draft.md' => "# Draft\n\nWords"]);
    resolve(IndexVault::class)->handle($vault);

    visit(route('notes.show', [$vault, Note::query()->sole()]))
        ->click('[aria-label="Note actions"]')
        ->click('@enter-focus')
        ->assertSeeIn('@focus-words', '2 words')
        ->click('@note-editor')
        ->click('@focus-goal')
        ->click('@note-editor')
        ->assertMissing('@focus-goal-input')
        ->assertSeeIn('@focus-goal', 'Set a goal')
        ->click('@focus-goal')
        ->fill('@focus-goal-input', '250')
        ->keys('@focus-goal-input', 'Enter')
        ->assertSeeIn('@focus-words', '2 / 250 words')
        ->click('@exit-focus')
        ->assertMissing('@focus-bar')
        // The goal reaches the text once the properties request returns; then it autosaves.
        ->assertSeeIn('@note-editor', '1 property')
        ->assertSee('Saved')
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Draft.md'))->toStartWith("---\nword_goal: 250\n---\n");
});
