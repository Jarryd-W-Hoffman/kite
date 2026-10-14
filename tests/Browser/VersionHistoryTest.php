<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('shows what each version changed and restores an older one', function (): void {
    $vault = vaultWithFiles(['Plan.md' => "# Plan\n\nLine one"]);
    resolve(IndexVault::class)->handle($vault);
    $note = Note::query()->sole();

    $page = visit(route('notes.show', [$vault, $note]))
        ->click('@note-editor')
        ->keys('@note-editor', ['Control+End', 'Enter'])
        ->typeSlowly('@note-editor', 'Line two')
        ->wait(1)
        ->assertSee('Saved')
        ->click('@note-actions')
        ->click('@open-history')
        ->assertCount('@version', 2)
        ->assertSeeIn('@diff-stats', '+1 −0')
        ->assertSeeIn('[data-diff=added]', 'Line two')
        ->click('@mode-current')
        ->assertSee('Identical to the note now.')
        ->click('[data-test=version-list] li:nth-child(2) [data-test=version]')
        ->assertSee('This is the oldest version Kite has.')
        ->click('@mode-current')
        ->assertSeeIn('[data-diff=added]', 'Line two')
        ->click('@restore-version')
        ->assertMissing('@version-history')
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Plan.md'))->toBe("# Plan\n\nLine one")
        ->and($note->versions()->count())->toBe(3);

    $page->assertScript("document.querySelector('.cm-content').textContent.includes('Line two')", false);
});
