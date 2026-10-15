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
    ) {
        //
    }

    /**
     * Trash a folder with everything in it and drop its notes from the index. Links that pointed
     * at those notes become unresolved rather than disappearing.
     */
    public function handle(Vault $vault, string $path): void
    {
        $path = $this->files->normalise($path);

        throw_unless($this->files->folderExists($vault, $path), InvalidArgumentException::class, "The folder [{$path}] does not exist.");

        $this->files->trash($vault, $path);
        $this->comments->trash($vault, $path, folder: true);

        DB::transaction(function () use ($vault, $path): void {
            Note::query()
                ->where('vault_id', $vault->id)
                ->whereRaw('substr(path, 1, ?) = ?', [mb_strlen($path) + 1, $path.'/'])
                ->get(['id', 'vault_id', 'path'])
                ->each(fn (Note $note): ?bool => $note->delete());

            $this->resolveLinks->handle($vault);
        });

        $this->indexAttachments->handle($vault);

        Vault::markTreeChanged($vault->id);
    }
}
