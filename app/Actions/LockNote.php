<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Services\CommentStore;
use App\Services\NoteLocks;
use App\Services\VaultFilesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use SensitiveParameter;

final readonly class LockNote
{
    public function __construct(
        private VaultFilesystem $files,
        private NoteLocks $locks,
        private CommentStore $comments,
        private RecordNoteVersion $recordVersion,
        private RewriteLockedLinks $rewriteLockedLinks,
        private IndexNote $indexNote,
    ) {
        //
    }

    /**
     * Encrypt a note in place. Everything that kept its text readable goes: its plain version
     * history and its comments (which quote it), and the database pages and search entries they
     * used are overwritten rather than just freed.
     */
    public function handle(Note $note, #[SensitiveParameter] string $key): Note
    {
        $vault = $note->vault;

        // Renames from while the vault was locked are for the notes locked then, not this one.
        $this->rewriteLockedLinks->handle($vault, $key);

        $contents = $this->files->read($vault, $note->path);

        throw_if($this->locks->isLocked($contents), InvalidArgumentException::class, 'That note is already locked.');

        $locked = $this->locks->lock($contents, $key);

        $this->files->write($vault, $note->path, $locked);
        File::delete($this->comments->path($vault, $note->path));

        DB::statement('PRAGMA secure_delete = ON');

        try {
            DB::transaction(function () use ($note, $locked): void {
                $note->versions()->delete();
                $this->recordVersion->handle($note, null, $locked);
            });

            $note = $this->indexNote->handle($vault, $note->path);

            // Merge the search index so the deleted entry for the note's text is really gone.
            DB::statement("INSERT INTO notes_fts(notes_fts) VALUES('optimize')");
        } finally {
            DB::statement('PRAGMA secure_delete = OFF');
        }

        return $note;
    }
}
