<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Vault;
use Illuminate\Http\RedirectResponse;

final readonly class HomeController
{
    /**
     * Re-open the most recently used vault, or ask for a folder on first launch.
     */
    public function __invoke(): RedirectResponse
    {
        $vault = Vault::query()->latest('opened_at')->first();

        return $vault instanceof Vault
            ? to_route('vaults.show', $vault)
            : to_route('vaults.index');
    }
}
