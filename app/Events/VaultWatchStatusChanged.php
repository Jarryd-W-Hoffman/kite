<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * The native file watcher for a vault started or stopped. Broadcast to the desktop window.
 */
final readonly class VaultWatchStatusChanged
{
    use Dispatchable;

    public function __construct(
        public int $vaultId,
        public bool $watching,
    ) {
        //
    }

    /**
     * @return list<string>
     */
    public function broadcastOn(): array
    {
        return ['nativephp'];
    }
}
