<?php

declare(strict_types=1);

namespace App\Actions;

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
    ) {
        //
    }

    public function handle(Note $note): void
    {
        $vault = $note->vault;

        $this->files->trash($vault, $note->path);
        $this->comments->trash($vault, $note->path);

        DB::transaction(function () use ($note, $vault): void {
            $note->delete();

            $this->resolveLinks->handle($vault);
        });
    }
}
