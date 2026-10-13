<?php

declare(strict_types=1);

use App\Events\VaultChanged;
use App\Events\VaultWatchStatusChanged;

it('broadcasts vault events to the desktop window', function (): void {
    expect(new VaultChanged(1)->broadcastOn())->toBe(['nativephp'])
        ->and(new VaultWatchStatusChanged(1, watching: true)->broadcastOn())->toBe(['nativephp']);
});
