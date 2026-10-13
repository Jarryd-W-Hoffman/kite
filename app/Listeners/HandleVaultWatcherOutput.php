<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\IndexAttachments;
use App\Actions\IndexVault;
use App\Events\VaultChanged;
use App\Events\VaultWatchStatusChanged;
use App\Models\Vault;
use App\Services\IndexingProgress;
use App\Services\VaultWatcher;
use Native\Desktop\Events\ChildProcess\MessageReceived;

final readonly class HandleVaultWatcherOutput
{
    public function __construct(
        private VaultWatcher $watcher,
        private IndexingProgress $progress,
        private IndexVault $indexVault,
        private IndexAttachments $indexAttachments,
    ) {
        //
    }

    /**
     * Each line the watcher prints is JSON: {"root": "...", "ready": true} or {"root": "...", "paths": [...]}.
     */
    public function handle(MessageReceived $event): void
    {
        if ($event->alias !== VaultWatcher::ALIAS || ! is_string($event->data)) {
            return;
        }

        foreach (preg_split('/\R/', mb_trim($event->data)) ?: [] as $line) {
            $message = json_decode($line, true);

            $root = is_array($message) ? ($message['root'] ?? null) : null;
            $vault = is_string($root) ? Vault::query()->where('path', $root)->first() : null;

            if (! $vault instanceof Vault) {
                continue;
            }

            if (($message['ready'] ?? false) === true) {
                $this->watcher->markReady($vault);

                event(new VaultWatchStatusChanged($vault->id, watching: true));

                continue;
            }

            $this->sync($vault);
        }
    }

    private function sync(Vault $vault): void
    {
        if ($this->progress->get($vault) !== null) {
            return;
        }

        $changes = array_sum($this->indexVault->handle($vault)) + $this->indexAttachments->handle($vault);

        if ($changes > 0) {
            event(new VaultChanged($vault->id));
        }
    }
}
