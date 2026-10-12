<?php

declare(strict_types=1);

use App\Actions\DeleteNote;
use App\Actions\IndexVault;
use App\Models\Note;
use App\Models\NoteLink;
use Illuminate\Support\Facades\DB;

it('deletes the file and re-resolves links to a remaining note with the same name', function (): void {
    $vault = vaultWithFiles([
        'Source.md' => 'See [[Topic]]',
        'Topic.md' => '# Root topic',
        'Archive/Topic.md' => '# Archived topic',
    ]);

    resolve(IndexVault::class)->handle($vault);

    $root = Note::query()->where('path', 'Topic.md')->sole();

    expect(NoteLink::query()->sole()->target_note_id)->toBe($root->id);

    resolve(DeleteNote::class)->handle($root);

    expect(file_exists($vault->path.'/Topic.md'))->toBeFalse()
        ->and(Note::query()->count())->toBe(2)
        ->and(DB::table('notes_fts')->count())->toBe(2)
        ->and(NoteLink::query()->sole()->target_note_id)->toBe(Note::query()->where('path', 'Archive/Topic.md')->value('id'));
});
