<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Models\Vault;
use App\Services\CommentStore;
use App\Services\VaultFilesystem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class DeleteFolder
{
    public function __construct(
        private VaultFilesystem $files,
        private ResolveLinks $resolveLinks,
        private IndexAttachments $indexAttachments,
        private CommentStore $comments,
        private ArchiveNote $archive,
    ) {
        //
    }

    /**
     * Trash a folder with everything in it and drop its notes from the index, keeping their
     * history for a while so Kite can restore them. Links that pointed at those notes become
     * unresolved rather than disappearing.
     */
    public function handle(Vault $vault, string $path): void
    {
        $path = $this->files->normalise($path);

        throw_unless($this->files->folderExists($vault, $path), InvalidArgumentException::class, "The folder [{$path}] does not exist.");

        $notes = Note::query()
            ->where('vault_id', $vault->id)
            ->whereRaw('substr(path, 1, ?) = ?', [mb_strlen($path) + 1, $path.'/'])
            ->get();

        foreach ($notes as $note) {
            $this->archive->handle($note, $this->files->exists($vault, $note->path) ? $this->files->read($vault, $note->path) : null);
        }

        $this->files->trash($vault, $path);
        $this->comments->trash($vault, $path, folder: true);

        DB::transaction(function () use ($vault, $notes): void {
            $notes->each(fn (Note $note): ?bool => $note->delete());

            $this->resolveLinks->handle($vault);
        });

        $this->indexAttachments->handle($vault);

        Vault::markTreeChanged($vault->id);
    }
}
