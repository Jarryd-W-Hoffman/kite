<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\VersionSource;
use App\Models\Note;
use App\Services\VaultFilesystem;

final readonly class SaveNote
{
    public function __construct(
        private VaultFilesystem $files,
        private IndexNote $indexNote,
        private RecordNoteVersion $recordVersion,
    ) {
        //
    }

    public function handle(Note $note, string $contents, VersionSource $source = VersionSource::Edit): Note
    {
        $previous = $this->files->exists($note->vault, $note->path)
            ? $this->files->read($note->vault, $note->path)
            : null;

        $this->files->write($note->vault, $note->path, $contents);
        $this->recordVersion->handle($note, $previous, $contents, $source);

        return $this->indexNote->handle($note->vault, $note->path);
    }
}
