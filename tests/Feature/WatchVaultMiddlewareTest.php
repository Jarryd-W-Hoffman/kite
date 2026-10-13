<?php

declare(strict_types=1);

use App\Services\VaultWatcher;
use Native\Desktop\Facades\ChildProcess;
use Native\Desktop\Http\Middleware\PreventRegularBrowserAccess;

it('starts a watcher when a vault page is opened in the desktop app', function (): void {
    config(['nativephp-internal.running' => true]);

    $processes = ChildProcess::fake();
    $vault = vaultWithFiles();

    $this->withoutMiddleware(PreventRegularBrowserAccess::class)
        ->get(route('vaults.show', $vault))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('watching', false));

    $processes->assertNode(fn (array $cmd, string $alias, ?array $env, bool $persistent): bool => $alias === VaultWatcher::ALIAS && $cmd[1] === $vault->path);
});

it('does not start watchers in a regular browser', function (): void {
    $processes = ChildProcess::fake();

    $this->get(route('vaults.show', vaultWithFiles()))->assertOk();

    expect($processes->nodes)->toBe([]);
});
