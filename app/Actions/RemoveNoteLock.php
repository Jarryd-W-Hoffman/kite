<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Services\NoteLocks;
use App\Services\VaultFilesystem;
use InvalidArgumentException;
use SensitiveParameter;

final readonly class RemoveNoteLock
{
    public function __construct(
        private VaultFilesystem $files,
        private NoteLocks $locks,
        private SaveNote $saveNote,
    ) {
        //
    }

    /**
     * Turn a locked note back into plain text. Its earlier, encrypted versions stay in its history,
     * readable while the vault is unlocked.
     */
    public function handle(Note $note, #[SensitiveParameter] string $key): Note
    {
        $contents = $this->locks->unlockNote($this->files->read($note->vault, $note->path), $key);

        throw_if($contents === null, InvalidArgumentException::class, 'That note is not locked with this key.');

        return $this->saveNote->handle($note, $contents);
    }
}
