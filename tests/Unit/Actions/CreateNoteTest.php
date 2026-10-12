<?php

declare(strict_types=1);

use App\Actions\CreateNote;
use App\Actions\IndexVault;
use App\Models\NoteLink;

it('creates a markdown file named after the title', function (): void {
    $vault = vaultWithFiles(['Index.md' => 'Waiting for [[Fresh Idea]]']);

    resolve(IndexVault::class)->handle($vault);

    $note = resolve(CreateNote::class)->handle($vault, 'Fresh Idea');

    expect($note->path)->toBe('Fresh Idea.md')
        ->and(file_get_contents($vault->path.'/Fresh Idea.md'))->toBe("# Fresh Idea\n\n")
        ->and(NoteLink::query()->sole()->target_note_id)->toBe($note->id);
});

it('creates notes inside folders with custom contents', function (): void {
    $vault = vaultWithFiles();

    $note = resolve(CreateNote::class)->handle($vault, 'Plan', 'Projects/Kite', 'Body');

    expect($note->path)->toBe('Projects/Kite/Plan.md')
        ->and(file_get_contents($vault->path.'/Projects/Kite/Plan.md'))->toBe('Body');
});

it('sanitises titles and avoids overwriting existing files', function (): void {
    $vault = vaultWithFiles(['What is it.md' => 'taken', 'What is it 1.md' => 'taken']);

    $createNote = resolve(CreateNote::class);

    expect($createNote->handle($vault, 'What/is: it?', '')->path)->toBe('What is it 2.md')
        ->and($createNote->handle($vault, ' ... ')->path)->toBe('Untitled.md')
        ->and(file_get_contents($vault->path.'/What is it.md'))->toBe('taken');
});
