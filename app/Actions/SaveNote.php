<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Services\VaultFilesystem;

final readonly class SaveNote
{
    public function __construct(
        private VaultFilesystem $files,
        private IndexNote $indexNote,
    ) {
        //
    }

    public function handle(Note $note, string $contents): Note
    {
        $this->files->write($note->vault, $note->path, $contents);

        return $this->indexNote->handle($note->vault, $note->path);
    }
}
