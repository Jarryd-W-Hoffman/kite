<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Actions\RenameNote;
use App\Models\Note;
use App\Models\NoteLink;

it('moves the note and rewrites links that pointed at it', function (): void {
    $vault = vaultWithFiles([
        'Old Name.md' => "# Old\nSelf [[Old Name#Top]]",
        'A.md' => 'See [[Old Name]], [[old name#Part|alias]], ![[Old Name]] and `[[Other]]`',
        'B.md' => 'Path link [[Folder/../Old Name]] [[./Old Name.md|file]]',
        'C.md' => 'Unrelated [[Other]]',
    ]);

    resolve(IndexVault::class)->handle($vault);

    $note = Note::query()->where('path', 'Old Name.md')->sole();
    $unrelatedModifiedAt = filemtime($vault->path.'/C.md');

    $renamed = resolve(RenameNote::class)->handle($note, 'Archive/New Name');

    expect($renamed->path)->toBe('Archive/New Name.md')
        ->and($renamed->id)->toBe($note->id)
        ->and(file_exists($vault->path.'/Old Name.md'))->toBeFalse()
        ->and(file_get_contents($vault->path.'/Archive/New Name.md'))->toBe("# Old\nSelf [[New Name#Top]]")
        ->and(file_get_contents($vault->path.'/A.md'))->toBe('See [[New Name]], [[New Name#Part|alias]], ![[New Name]] and `[[Other]]`')
        ->and(file_get_contents($vault->path.'/B.md'))->toBe('Path link [[Folder/../Old Name]] [[New Name|file]]')
        ->and(filemtime($vault->path.'/C.md'))->toBe($unrelatedModifiedAt)
        ->and(NoteLink::query()->where('target_note_id', $note->id)->count())->toBe(5);
});

it('rewrites path style links using the new path', function (): void {
    $vault = vaultWithFiles([
        'Projects/Plan.md' => '# Plan',
        'Index.md' => 'See [[Projects/Plan|the plan]]',
    ]);

    resolve(IndexVault::class)->handle($vault);

    resolve(RenameNote::class)->handle(Note::query()->where('path', 'Projects/Plan.md')->sole(), 'Archive/Plan.md');

    expect(file_get_contents($vault->path.'/Index.md'))->toBe('See [[Archive/Plan|the plan]]');
});

it('is a no-op when the path does not change', function (): void {
    $vault = vaultWithFiles(['Note.md' => '# Note']);

    resolve(IndexVault::class)->handle($vault);

    $note = Note::query()->sole();

    expect(resolve(RenameNote::class)->handle($note, 'Note'))->toBe($note);
});

it('allows changing only the case of a file name', function (): void {
    $vault = vaultWithFiles(['note.md' => '# Note']);

    resolve(IndexVault::class)->handle($vault);

    expect(resolve(RenameNote::class)->handle(Note::query()->sole(), 'Note.md')->path)->toBe('Note.md');
});

it('refuses to overwrite another note', function (): void {
    $vault = vaultWithFiles(['A.md' => 'a', 'B.md' => 'b']);

    resolve(IndexVault::class)->handle($vault);

    resolve(RenameNote::class)->handle(Note::query()->where('path', 'A.md')->sole(), 'B.md');
})->throws(InvalidArgumentException::class, 'already exists');
