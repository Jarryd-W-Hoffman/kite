<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RecordLaunch;
use App\Enums\WelcomeReason;
use App\Models\Vault;
use Illuminate\Http\RedirectResponse;

final readonly class HomeController
{
    /**
     * Re-open the most recently used vault, on its Welcome tab when that's due, or ask for a folder
     * on first launch.
     */
    public function __invoke(RecordLaunch $launch): RedirectResponse
    {
        $reason = $launch->handle();
        $vault = Vault::query()->latest('opened_at')->first();

        if (! $vault instanceof Vault) {
            return to_route('vaults.index');
        }

        return match ($reason) {
            WelcomeReason::Updated => to_route('welcome.show', [$vault, 'updated' => 1]),
            WelcomeReason::Startup => to_route('welcome.show', $vault),
            null => to_route('vaults.show', $vault),
        };
    }
}
