<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Models\Vault;
use App\Services\VaultFilesystem;
use InvalidArgumentException;

final readonly class RenameFolder
{
    public function __construct(
        private VaultFilesystem $files,
        private RenameNote $renameNote,
        private IndexAttachments $indexAttachments,
    ) {
        //
    }

    /**
     * Rename or move a folder and return its new path. Notes move through RenameNote so links that
     * name them by path are rewritten; everything else follows afterwards.
     */
    public function handle(Vault $vault, string $from, string $to): string
    {
        $from = $this->files->normalise($from);
        $to = $this->files->normalise($to);

        if ($from === $to) {
            return $to;
        }

        throw_unless($this->files->folderExists($vault, $from), InvalidArgumentException::class, "The folder [{$from}] does not exist.");
        throw_if(str_starts_with($to.'/', $from.'/'), InvalidArgumentException::class, "The folder [{$from}] cannot be moved inside itself.");
        throw_if($this->files->isIgnored($to), InvalidArgumentException::class, "The folder [{$to}] is ignored.");

        $caseOnly = mb_strtolower($from) === mb_strtolower($to);

        throw_if(
            ! $caseOnly && ($this->files->folderExists($vault, $to) || $this->files->exists($vault, $to)),
            InvalidArgumentException::class,
            "The folder [{$to}] already exists.",
        );

        // Case-insensitive filesystems treat "notes" and "Notes" as the same folder, so a
        // case-only rename goes through a temporary name.
        if ($caseOnly) {
            $temporary = $to.'~'.bin2hex(random_bytes(3));

            return $this->move($vault, $this->move($vault, $from, $temporary), $to);
        }

        return $this->move($vault, $from, $to);
    }

    private function move(Vault $vault, string $from, string $to): string
    {
        $notes = Note::query()
            ->where('vault_id', $vault->id)
            ->whereRaw('substr(path, 1, ?) = ?', [mb_strlen($from) + 1, $from.'/'])
            ->orderBy('path')
            ->get();

        $moves = [];

        foreach ($notes as $note) {
            $moves[] = [$note, $to.mb_substr($note->path, mb_strlen($from))];
        }

        $this->renameNote->handleMany($vault, $moves);

        $this->files->moveFolderContents($vault, $from, $to);

        Vault::markTreeChanged($vault->id);
        $this->indexAttachments->handle($vault);

        return $to;
    }
}
