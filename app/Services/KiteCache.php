<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * The cache store for state shared between Kite's processes (web server, queue worker, watchers).
 *
 * It must be visible across processes and independent of database transactions, so it defaults
 * to the file store rather than the app's database cache.
 */
final readonly class KiteCache
{
    public function store(): Repository
    {
        $store = config('kite.cache_store');

        return Cache::store(is_string($store) ? $store : null);
    }
}
