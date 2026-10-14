<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('shows embedded notes in the preview and opens them from the embed title', function (): void {
    $vault = vaultWithFiles([
        'Home.md' => "# Home\n\n![[Recipe#Ingredients]]",
        'Recipe.md' => "# Recipe\n\n## Ingredients\n- Flour\n- Water\n\n## Method\nMix.",
    ]);
    resolve(IndexVault::class)->handle($vault);

    $page = visit(route('notes.show', [$vault, Note::query()->where('path', 'Home.md')->sole()]));

    $page->assertSeeIn('.note-embed', 'Flour')
        ->assertDontSeeIn('.note-embed', 'Mix.')
        ->click('.note-embed-title')
        ->assertSeeIn('@note-preview', 'Mix.')
        ->assertScript('window.location.hash', '#ingredients')
        ->assertNoJavaScriptErrors();
});
