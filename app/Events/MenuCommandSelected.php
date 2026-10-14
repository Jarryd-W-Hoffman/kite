<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A command chosen from the native app menu. NativePHP dispatches it with the clicked item and,
 * because it is on the "nativephp" channel, forwards it straight back to the window to run.
 */
final readonly class MenuCommandSelected
{
    use Dispatchable;

    public string $command;

    /**
     * NativePHP spreads the clicked item (and key combo) into the constructor.
     */
    public function __construct(mixed ...$payload)
    {
        $item = $payload['item'] ?? $payload[0] ?? [];
        $id = is_array($item) ? ($item['id'] ?? null) : null;

        $this->command = is_string($id) ? $id : '';
    }

    /**
     * @return list<string>
     */
    public function broadcastOn(): array
    {
        return ['nativephp'];
    }
}
