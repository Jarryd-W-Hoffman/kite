<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Vault;
use Native\Desktop\Facades\ChildProcess;

/**
 * Runs a native file watcher for the open vault as a NativePHP child process.
 */
final readonly class VaultWatcher
{
    public const string ALIAS = 'kite-vault-watcher';

    private const string STATE_KEY = 'kite:watcher';

    public function __construct(
        private KiteCache $cache,
        private AttachmentTypes $types,
    ) {
        //
    }

    /**
     * Start watching the vault, replacing any watcher for another vault. Only runs in the desktop app.
     */
    public function start(Vault $vault): bool
    {
        if (! config('nativephp-internal.running')) {
            return false;
        }

        $state = $this->state();

        if ($state !== null && $state['vault_id'] === $vault->id && ChildProcess::get(self::ALIAS) !== null) {
            return true;
        }

        ChildProcess::stop(self::ALIAS);

        ChildProcess::node([
            resource_path('watcher/vault-watcher.mjs'),
            $vault->path,
            implode(',', ['md', ...$this->types->extensions()]),
        ], self::ALIAS, persistent: true);

        $this->cache->store()->forever(self::STATE_KEY, ['vault_id' => $vault->id, 'ready' => false]);

        return true;
    }

    public function markReady(Vault $vault): void
    {
        $this->cache->store()->forever(self::STATE_KEY, ['vault_id' => $vault->id, 'ready' => true]);
    }

    /**
     * Forget the watcher, returning the vault it was watching.
     */
    public function markStopped(): ?int
    {
        $vaultId = $this->state()['vault_id'] ?? null;

        $this->cache->store()->forget(self::STATE_KEY);

        return $vaultId;
    }

    public function isWatching(Vault $vault): bool
    {
        $state = $this->state();

        return $state !== null && $state['vault_id'] === $vault->id && $state['ready'];
    }

    /**
     * @return array{vault_id: int, ready: bool}|null
     */
    private function state(): ?array
    {
        $state = $this->cache->store()->get(self::STATE_KEY);

        if (! is_array($state) || ! is_int($state['vault_id'] ?? null) || ! is_bool($state['ready'] ?? null)) {
            return null;
        }

        return ['vault_id' => $state['vault_id'], 'ready' => $state['ready']];
    }
}
