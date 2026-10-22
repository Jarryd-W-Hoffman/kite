<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\DeletedNote;
use App\Models\Note;
use App\Services\CommentStore;
use App\Services\VaultFilesystem;
use Illuminate\Support\Facades\DB;

final readonly class DeleteNote
{
    public function __construct(
        private VaultFilesystem $files,
        private ResolveLinks $resolveLinks,
        private CommentStore $comments,
        private ArchiveNote $archive,
    ) {
        //
    }

    /**
     * Send a note's file to the trash and drop it from the index, keeping its history for a while
     * so Kite can restore it. Returns what was kept, if anything.
     */
    public function handle(Note $note): ?DeletedNote
    {
        $vault = $note->vault;

        $deleted = $this->archive->handle($note, $this->files->exists($vault, $note->path) ? $this->files->read($vault, $note->path) : null);

        $this->files->trash($vault, $note->path);
        $this->comments->trash($vault, $note->path);

        DB::transaction(function () use ($note, $vault): void {
            $note->delete();

            $this->resolveLinks->handle($vault);
        });

        return $deleted;
    }
}
