<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by the native global shortcut or tray menu to open the quick capture window.
 */
final readonly class QuickCaptureRequested
{
    use Dispatchable;

    /**
     * @var array<array-key, mixed>
     */
    public array $payload;

    /**
     * NativePHP spreads the shortcut or menu payload into the constructor.
     */
    public function __construct(mixed ...$payload)
    {
        $this->payload = $payload;
    }
}
