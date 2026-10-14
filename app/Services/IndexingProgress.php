<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Vault;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use RuntimeException;

/**
 * Shares indexing progress between the queue worker and the app via the cache.
 */
final readonly class IndexingProgress
{
    private const int TTL_SECONDS = 3600;

    public function __construct(private KiteCache $cache)
    {
        //
    }

    /**
     * A lock that ensures only one index run per vault happens at a time.
     */
    public function lock(Vault $vault): Lock
    {
        $store = $this->store()->getStore();

        throw_unless($store instanceof LockProvider, RuntimeException::class, 'The indexing cache store must support atomic locks.');

        return $store->lock("kite:index:{$vault->id}", self::TTL_SECONDS);
    }

    public function start(Vault $vault, int $total = 0): void
    {
        $this->update($vault, 0, $total);
    }

    public function update(Vault $vault, int $done, int $total): void
    {
        $this->store()->put($this->key($vault), ['done' => $done, 'total' => $total], self::TTL_SECONDS);
    }

    public function finish(Vault $vault): void
    {
        $this->store()->forget($this->key($vault));
    }

    /**
     * @return array{done: int, total: int}|null
     */
    public function get(Vault $vault): ?array
    {
        $progress = $this->store()->get($this->key($vault));

        if (! is_array($progress) || ! is_int($progress['done'] ?? null) || ! is_int($progress['total'] ?? null)) {
            return null;
        }

        return ['done' => $progress['done'], 'total' => $progress['total']];
    }

    private function store(): Repository
    {
        return $this->cache->store();
    }

    private function key(Vault $vault): string
    {
        return "kite:indexing:{$vault->id}";
    }
}
