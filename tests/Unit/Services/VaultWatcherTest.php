<?php

declare(strict_types=1);

use App\Models\Vault;
use App\Services\VaultWatcher;
use Native\Desktop\Facades\ChildProcess;

beforeEach(function (): void {
    config(['nativephp-internal.running' => true]);
});

it('does nothing outside the desktop app', function (): void {
    config(['nativephp-internal.running' => false]);

    $processes = ChildProcess::fake();

    expect(resolve(VaultWatcher::class)->start(Vault::factory()->create()))->toBeFalse()
        ->and($processes->nodes)->toBe([]);
});

it('starts a persistent node watcher for the vault', function (): void {
    $processes = ChildProcess::fake();
    $vault = Vault::factory()->create(['path' => '/notes/kite']);
    $watcher = resolve(VaultWatcher::class);

    expect($watcher->start($vault))->toBeTrue()
        ->and($watcher->isWatching($vault))->toBeFalse();

    $processes->assertNode(fn (array $cmd, string $alias, ?array $env, bool $persistent): bool => $alias === VaultWatcher::ALIAS
        && $persistent
        && $cmd[0] === resource_path('watcher/vault-watcher.mjs')
        && $cmd[1] === '/notes/kite'
        && str_starts_with((string) $cmd[2], 'md,'));

    $watcher->markReady($vault);

    expect($watcher->isWatching($vault))->toBeTrue();
});

it('keeps a running watcher for the same vault and replaces it for another', function (): void {
    $processes = ChildProcess::fake();
    $first = Vault::factory()->create();
    $second = Vault::factory()->create();
    $watcher = resolve(VaultWatcher::class);

    $watcher->start($first);
    $watcher->start($first);

    expect($processes->nodes)->toHaveCount(1);

    $watcher->start($second);

    expect($processes->nodes)->toHaveCount(2)
        ->and($processes->stops)->toBe([VaultWatcher::ALIAS, VaultWatcher::ALIAS])
        ->and($watcher->isWatching($first))->toBeFalse();
});

it('forgets the watcher when it stops', function (): void {
    ChildProcess::fake();
    $vault = Vault::factory()->create();
    $watcher = resolve(VaultWatcher::class);

    expect($watcher->markStopped())->toBeNull();

    $watcher->start($vault);
    $watcher->markReady($vault);

    expect($watcher->markStopped())->toBe($vault->id)
        ->and($watcher->isWatching($vault))->toBeFalse();
});
