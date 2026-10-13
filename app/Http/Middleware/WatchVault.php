<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Vault;
use App\Services\VaultWatcher;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Make sure the vault being viewed has a native file watcher running in the desktop app.
 */
final readonly class WatchVault
{
    public function __construct(private VaultWatcher $watcher)
    {
        //
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $vault = $request->route('vault');

        if ($vault instanceof Vault) {
            $this->watcher->start($vault);
        }

        return $next($request);
    }
}
