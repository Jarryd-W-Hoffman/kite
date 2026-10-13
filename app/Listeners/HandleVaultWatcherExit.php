<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\VaultWatchStatusChanged;
use App\Services\VaultWatcher;
use Native\Desktop\Events\ChildProcess\ProcessExited;

final readonly class HandleVaultWatcherExit
{
    public function __construct(private VaultWatcher $watcher)
    {
        //
    }

    /**
     * When the watcher dies (e.g. the OS ran out of file watches) the window falls back to polling.
     */
    public function handle(ProcessExited $event): void
    {
        if ($event->alias !== VaultWatcher::ALIAS) {
            return;
        }

        $vaultId = $this->watcher->markStopped();

        if ($vaultId !== null) {
            event(new VaultWatchStatusChanged($vaultId, watching: false));
        }
    }
}
