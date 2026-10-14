<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('extracts a selection into a new linked note', function (): void {
    $vault = vaultWithFiles(['Projects/Plan.md' => "# Plan\n\nRisks to watch\n\nThe end."]);
    resolve(IndexVault::class)->handle($vault);

    visit(route('notes.show', [$vault, Note::query()->sole()]))
        ->click('@note-editor')
        ->keys('@note-editor', ['Control+Home', 'ArrowDown', 'ArrowDown', 'Shift+End'])
        ->click('@extract-selection')
        ->assertVisible('@extract-dialog')
        ->assertValue('title', 'Risks to watch')
        ->fill('title', 'Risks')
        ->click('Extract')
        ->assertMissing('@extract-dialog')
        ->assertSee('Extracted to “Risks”.')
        ->assertDontSeeIn('@note-editor', 'Risks to watch')
        ->assertSee('Saved')
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Projects/Risks.md'))->toBe("# Risks\n\nRisks to watch\n")
        ->and(file_get_contents($vault->path.'/Projects/Plan.md'))->toContain("# Plan\n\n[[Risks]]\n\nThe end.");
});

it('merges a note into another from the note menu', function (): void {
    $vault = vaultWithFiles([
        'Ideas.md' => "# Ideas\n\nShip faster.",
        'Roadmap.md' => '# Roadmap',
        'Home.md' => "# Home\n\nSee [[Ideas]].",
    ]);
    resolve(IndexVault::class)->handle($vault);

    visit(route('notes.show', [$vault, Note::query()->where('path', 'Ideas.md')->sole()]))
        ->click('[aria-label="Note actions"]')
        ->click('@merge-note')
        ->assertVisible('@merge-dialog')
        ->typeSlowly('@merge-search', 'road')
        ->click('@merge-option')
        ->click('Merge into “Roadmap”')
        ->assertPathEndsWith('/notes/'.Note::query()->where('path', 'Roadmap.md')->sole()->id)
        ->assertSee('Merged “Ideas” into this note.')
        ->assertSeeIn('@note-editor', 'Ship faster.')
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Home.md'))->toBe("# Home\n\nSee [[Roadmap]].")
        ->and(file_exists($vault->path.'/Ideas.md'))->toBeFalse();
});
