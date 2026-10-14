<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Files in a vault changed on disk and the index was updated. Broadcast to the desktop window.
 */
final readonly class VaultChanged
{
    use Dispatchable;

    public function __construct(public int $vaultId)
    {
        //
    }

    /**
     * NativePHP forwards events on the "nativephp" channel to the window as `Native.on()` events.
     *
     * @return list<string>
     */
    public function broadcastOn(): array
    {
        return ['nativephp'];
    }
}
