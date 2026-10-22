<?php

declare(strict_types=1);

use App\Actions\DeleteNote;
use App\Actions\IndexVault;
use App\Models\Note;

it('undoes deleting a note from the toast', function (): void {
    $vault = vaultWithFiles(['Keep.md' => '# Keep', 'Gone.md' => "# Gone\n\nStill here"]);
    resolve(IndexVault::class)->handle($vault);

    visitSplit(route('notes.show', [$vault, Note::query()->where('path', 'Gone.md')->sole()]))
        ->click('@note-actions')
        ->click('@delete-note')
        ->assertSeeIn('@delete-note-dialog', 'restore it from Recently deleted')
        ->click('Delete note')
        ->assertSee('Deleted “Gone”.')
        ->click('Undo')
        ->assertSee('Restored “Gone”.')
        ->assertSeeIn('@note-editor', 'Still here')
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Gone.md'))->toBe("# Gone\n\nStill here");
});

it('restores a deleted note from recently deleted', function (): void {
    $vault = vaultWithFiles(['Keep.md' => '# Keep', 'Plans.md' => "# Plans\n\nThe plan"]);
    resolve(IndexVault::class)->handle($vault);
    resolve(DeleteNote::class)->handle(Note::query()->where('path', 'Plans.md')->sole());

    visitSplit(route('notes.show', [$vault, Note::query()->sole()]))
        ->click($vault->name)
        ->click('@recently-deleted')
        ->assertSeeIn('@deleted-note-list', 'Plans.md')
        ->assertSeeIn('@deleted-note-contents', 'The plan')
        ->click('@restore-deleted-note')
        ->assertSee('Restored “Plans”.')
        ->assertSeeIn('@note-editor', 'The plan')
        ->assertNoJavaScriptErrors();

    expect(Note::query()->where('path', 'Plans.md')->exists())->toBeTrue();
});
