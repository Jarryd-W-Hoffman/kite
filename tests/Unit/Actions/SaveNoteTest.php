<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Actions\SaveNote;
use App\Models\Note;

it('writes the note to disk and re-indexes it', function (): void {
    $vault = vaultWithFiles([
        'Note.md' => '# Draft',
        'Other.md' => '# Other',
    ]);

    resolve(IndexVault::class)->handle($vault);

    $note = Note::query()->where('path', 'Note.md')->sole();

    $saved = resolve(SaveNote::class)->handle($note, "# Final\nSee [[Other]]");

    expect(file_get_contents($vault->path.'/Note.md'))->toBe("# Final\nSee [[Other]]")
        ->and($saved->title)->toBe('Final')
        ->and($saved->outgoingLinks()->sole()->target_note_id)->toBe(Note::query()->where('path', 'Other.md')->value('id'))
        ->and(glob($vault->path.'/.*.tmp'))->toBe([]);
});
