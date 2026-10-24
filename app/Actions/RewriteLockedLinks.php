<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Models\PendingLinkRewrite;
use App\Models\Vault;
use App\Services\NoteLocks;
use App\Services\VaultFilesystem;
use SensitiveParameter;

final readonly class RewriteLockedLinks
{
    public function __construct(
        private VaultFilesystem $files,
        private NoteLocks $locks,
        private RewriteLinks $rewriteLinks,
        private SaveNote $saveNote,
    ) {
        //
    }

    /**
     * Point the links in the vault's locked notes at notes renamed while it was locked, in the
     * order they were renamed, now that its key is at hand. A note that won't open with the key
     * is left as it is.
     */
    public function handle(Vault $vault, #[SensitiveParameter] string $key): void
    {
        $renames = $vault->pendingLinkRewrites()->orderBy('id')->get();

        if ($renames->isEmpty()) {
            return;
        }

        $notes = Note::query()->where('vault_id', $vault->id)->where('locked', true)->get();

        foreach ($notes as $note) {
            $contents = $this->locks->unlockNote($this->files->read($vault, $note->path), $key);

            if ($contents === null) {
                continue;
            }

            $rewritten = $renames->reduce(
                fn (string $text, PendingLinkRewrite $rename): string => $this->rewriteLinks->rewrite($text, $rename->keys, $rename->path),
                $contents,
            );

            if ($rewritten !== $contents) {
                $this->saveNote->handle($note, $this->locks->lock($rewritten, $key));
            }
        }

        $vault->pendingLinkRewrites()->whereKey($renames->modelKeys())->delete();
    }
}
