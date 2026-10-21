<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;
use App\Services\Markdown\MarkdownParser;
use App\Services\NoteWriter;

it('updates a note it was told is new when it was added meanwhile', function (): void {
    $vault = vaultWithFiles(['A.md' => '# Old']);
    resolve(IndexVault::class)->handle($vault);
    $id = Note::query()->sole()->id;
    $revision = $vault->currentTreeRevision();

    $note = resolve(NoteWriter::class)->write($vault, 'A.md', '# New', ['size' => 5, 'modified_at' => 1], resolve(MarkdownParser::class)->parse('# New', 'A.md'), isNew: true);

    expect($note->id)->toBe($id)
        ->and($note->wasRecentlyCreated)->toBeFalse()
        ->and(Note::query()->sole()->title)->toBe('New')
        ->and($vault->currentTreeRevision())->toBe($revision + 1);
});
