<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\DeletedNote;
use App\Models\Note;
use App\Models\NoteVersion;
use App\Models\Vault;
use App\Services\VaultFilesystem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class RestoreDeletedNote
{
    public function __construct(
        private VaultFilesystem $files,
        private IndexNote $indexNote,
    ) {
        //
    }

    /**
     * Write a deleted note's last text back and give it its history and star again. It goes back
     * where it was, or beside it as "… (restored)" when something else has that path now.
     */
    public function handle(DeletedNote $deleted): Note
    {
        $vault = $deleted->vault;
        $version = $deleted->latestVersion();

        throw_unless($version instanceof NoteVersion, InvalidArgumentException::class, 'That deleted note has nothing to restore.');

        $path = $this->freePath($vault, $deleted->path);

        $this->files->write($vault, $path, $version->contents);

        return DB::transaction(function () use ($deleted, $vault, $path): Note {
            $note = $this->indexNote->handle($vault, $path);

            $this->reattach($deleted, $note);

            return $note->refresh();
        });
    }

    /**
     * Move a deleted note's history and star onto a note, and forget the deleted note.
     */
    public function reattach(DeletedNote $deleted, Note $note): void
    {
        $deleted->versions()->toBase()->update(['note_id' => $note->id, 'deleted_note_id' => null]);

        if ($deleted->starred_at !== null) {
            DB::table('notes')->where('id', $note->id)->update(['starred_at' => $deleted->starred_at]);
        }

        $deleted->delete();
    }

    private function freePath(Vault $vault, string $path): string
    {
        $stem = mb_substr($path, 0, -3);
        $candidate = $path;

        for ($n = 1; $this->files->exists($vault, $candidate); $n++) {
            $candidate = $stem.($n === 1 ? ' (restored)' : " (restored {$n})").'.md';
        }

        return $candidate;
    }
}
