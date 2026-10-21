<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('pauses saving when the note changed on disk, and keeps your version when asked', function (): void {
    $vault = vaultWithFiles(['Plan.md' => "# Plan\n\nLine one"]);
    resolve(IndexVault::class)->handle($vault);
    $note = Note::query()->sole();

    $page = visitSplit(route('notes.show', [$vault, $note]));

    file_put_contents($vault->path.'/Plan.md', "# Plan\n\nFrom another app");

    $page->click('@note-editor')
        ->keys('@note-editor', ['Control+End', 'Enter'])
        ->typeSlowly('@note-editor', 'Mine')
        ->wait(1)
        ->assertVisible('@save-conflict')
        ->assertSee('Changed on disk')
        ->click('@conflict-compare')
        ->assertSeeIn('[data-diff=removed]', 'From another app')
        ->assertSeeIn('[data-diff=added]', 'Mine');

    expect(file_get_contents($vault->path.'/Plan.md'))->toBe("# Plan\n\nFrom another app");

    $page->click('[role=dialog] [data-test=conflict-keep-mine]')
        ->wait(1)
        ->assertMissing('@save-conflict')
        ->assertSee('Saved')
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Plan.md'))->toBe("# Plan\n\nLine one\nMine");
});

it('drops your unsaved edits for the version on disk when asked', function (): void {
    $vault = vaultWithFiles(['Plan.md' => "# Plan\n\nLine one"]);
    resolve(IndexVault::class)->handle($vault);
    $note = Note::query()->sole();

    $page = visitSplit(route('notes.show', [$vault, $note]));

    file_put_contents($vault->path.'/Plan.md', "# Plan\n\nFrom another app");

    $page->click('@note-editor')
        ->keys('@note-editor', ['Control+End', 'Enter'])
        ->typeSlowly('@note-editor', 'Mine')
        ->wait(1)
        ->click('@conflict-take-disk')
        ->assertMissing('@save-conflict')
        ->assertSee('Saved')
        ->wait(1)
        ->assertNoJavaScriptErrors();

    $page->assertScript("document.querySelector('.cm-content').textContent.includes('From another app')", true);

    expect(file_get_contents($vault->path.'/Plan.md'))->toBe("# Plan\n\nFrom another app");
});
