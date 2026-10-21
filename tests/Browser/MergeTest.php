<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('merges changes made on disk with yours when they touch different lines', function (): void {
    $vault = vaultWithFiles(['Plan.md' => "# Plan\n\nFirst\n\nLast"]);
    resolve(IndexVault::class)->handle($vault);

    $page = visitSplit(route('notes.show', [$vault, Note::query()->sole()]));

    file_put_contents($vault->path.'/Plan.md', "# Plan\n\nFirst, changed elsewhere\n\nLast");

    $page->click('@note-editor')
        ->keys('@note-editor', ['Control+End', 'Enter'])
        ->typeSlowly('@note-editor', 'Mine')
        ->wait(1)
        ->assertVisible('@save-conflict')
        ->click('@conflict-merge')
        ->assertSeeIn('@merge-dialog', 'Everything merged by itself.')
        ->assertCount('@merge-auto', 2)
        ->click('@merge-save')
        ->wait(1)
        ->assertMissing('@save-conflict')
        ->assertSee('Saved')
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Plan.md'))->toBe("# Plan\n\nFirst, changed elsewhere\n\nLast\nMine");
});

it('lets you choose where both changed the same line', function (): void {
    $vault = vaultWithFiles(['Plan.md' => "# Plan\n\nLine"]);
    resolve(IndexVault::class)->handle($vault);

    $page = visitSplit(route('notes.show', [$vault, Note::query()->sole()]));

    file_put_contents($vault->path.'/Plan.md', "# Plan\n\nLine from disk");

    $page->click('@note-editor')
        ->keys('@note-editor', ['Control+End'])
        ->typeSlowly('@note-editor', ' of mine')
        ->wait(1)
        ->click('@conflict-merge')
        ->assertCount('@merge-conflict', 1)
        ->assertButtonDisabled('@merge-save')
        ->click('@merge-use-both')
        ->click('@merge-save')
        ->wait(1)
        ->assertSee('Saved')
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Plan.md'))->toBe("# Plan\n\nLine of mine\nLine from disk");
});

it("merges a sync app's conflicting copy into the note and deletes the copy", function (): void {
    $vault = vaultWithFiles([
        'Plans.md' => "# Plans\n\nShared\n\nOurs",
        "Plans (Jo's conflicted copy 2026-10-21).md" => "# Plans\n\nShared\n\nTheirs",
    ]);
    resolve(IndexVault::class)->handle($vault);

    visitSplit(route('notes.show', [$vault, Note::query()->where('path', 'Plans.md')->sole()]))
        ->assertSeeIn('@conflict-copies', "Plans (Jo's conflicted copy 2026-10-21).md")
        ->click('@merge-copy')
        ->assertCount('@merge-conflict', 1)
        ->click('@merge-all-theirs')
        ->click('@merge-save')
        ->assertSee('Merged the conflicting copy into “Plans” and deleted it.')
        ->assertMissing('@conflict-copies')
        ->assertSeeIn('@note-editor', 'Theirs')
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Plans.md'))->toBe("# Plans\n\nShared\n\nTheirs")
        ->and(Note::query()->pluck('path')->all())->toBe(['Plans.md']);
});

it('points a conflicting copy at its note', function (): void {
    $vault = vaultWithFiles(['Plans.md' => 'a', 'Plans.sync-conflict-20261021-101530-ABCD123.md' => 'b']);
    resolve(IndexVault::class)->handle($vault);

    visitSplit(route('notes.show', [$vault, Note::query()->where('path', 'like', '%sync-conflict%')->sole()]))
        ->assertSeeIn('@conflict-copy-of', 'This is a conflicting copy of Plans')
        ->click('Open it to merge this copy')
        ->assertVisible('@conflict-copies')
        ->assertNoJavaScriptErrors();
});
