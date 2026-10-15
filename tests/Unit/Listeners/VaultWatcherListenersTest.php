<?php

declare(strict_types=1);

use App\Events\VaultChanged;
use App\Events\VaultWatchStatusChanged;
use App\Listeners\HandleVaultWatcherExit;
use App\Listeners\HandleVaultWatcherOutput;
use App\Services\IndexingProgress;
use App\Services\VaultWatcher;
use Illuminate\Support\Facades\Event;
use Native\Desktop\Events\ChildProcess\MessageReceived;
use Native\Desktop\Events\ChildProcess\ProcessExited;
use Native\Desktop\Facades\ChildProcess;

beforeEach(function (): void {
    config(['nativephp-internal.running' => true]);
    ChildProcess::fake();
});

it('marks the watcher ready and re-indexes on changes', function (): void {
    Event::fake([VaultChanged::class, VaultWatchStatusChanged::class]);

    $vault = vaultWithFiles();
    $watcher = resolve(VaultWatcher::class);
    $watcher->start($vault);

    $listener = resolve(HandleVaultWatcherOutput::class);
    $line = fn (array $message): string => json_encode(['root' => $vault->path, ...$message]);

    $listener->handle(new MessageReceived(VaultWatcher::ALIAS, $line(['ready' => true])."\n"));

    expect($watcher->isWatching($vault))->toBeTrue();
    Event::assertDispatched(VaultWatchStatusChanged::class, fn ($event): bool => $event->vaultId === $vault->id && $event->watching);

    file_put_contents($vault->path.'/New.md', '# New');
    file_put_contents($vault->path.'/pic.png', 'png');

    $listener->handle(new MessageReceived(VaultWatcher::ALIAS, $line(['paths' => ['New.md']])."\r\n".$line(['paths' => ['pic.png']])));

    expect($vault->notes()->count())->toBe(1)
        ->and($vault->attachments()->count())->toBe(1);
    Event::assertDispatchedTimes(VaultChanged::class, 1);
});

it('ignores other processes, garbage and unknown vaults', function (): void {
    Event::fake([VaultChanged::class, VaultWatchStatusChanged::class]);

    $vault = vaultWithFiles(['A.md' => 'a']);
    $listener = resolve(HandleVaultWatcherOutput::class);

    $listener->handle(new MessageReceived('something-else', json_encode(['root' => $vault->path, 'paths' => []])));
    $listener->handle(new MessageReceived(VaultWatcher::ALIAS, ['not' => 'a string']));
    $listener->handle(new MessageReceived(VaultWatcher::ALIAS, "not json\n{\"root\": 5}\n".json_encode(['root' => '/elsewhere', 'paths' => []])));

    expect($vault->notes()->count())->toBe(0);
    Event::assertNothingDispatched();
});

it('does not re-index while a background index is running or when nothing changed', function (): void {
    Event::fake([VaultChanged::class]);

    $vault = vaultWithFiles(['A.md' => 'a']);
    $listener = resolve(HandleVaultWatcherOutput::class);
    $message = new MessageReceived(VaultWatcher::ALIAS, json_encode(['root' => $vault->path, 'paths' => ['A.md']]));

    resolve(IndexingProgress::class)->start($vault);
    $listener->handle($message);

    expect($vault->notes()->count())->toBe(0);

    resolve(IndexingProgress::class)->finish($vault);
    $listener->handle($message);
    $listener->handle($message);

    expect($vault->notes()->count())->toBe(1);
    Event::assertDispatchedTimes(VaultChanged::class, 1);
});

it('refreshes the workspace when folders change even if no note did', function (): void {
    Event::fake([VaultChanged::class]);

    $vault = vaultWithFiles();
    $listener = resolve(HandleVaultWatcherOutput::class);
    $line = fn (array $paths): string => json_encode(['root' => $vault->path, 'paths' => $paths]);

    $listener->handle(new MessageReceived(VaultWatcher::ALIAS, $line(['notes.txt'])));

    Event::assertNotDispatched(VaultChanged::class);

    $revision = $vault->currentTreeRevision();

    mkdir($vault->path.'/Projects');
    $listener->handle(new MessageReceived(VaultWatcher::ALIAS, $line(['Projects'])));

    Event::assertDispatchedTimes(VaultChanged::class, 1);
    expect($vault->currentTreeRevision())->toBeGreaterThan($revision);
});

it('falls back to polling when the watcher exits', function (): void {
    Event::fake([VaultWatchStatusChanged::class]);

    $vault = vaultWithFiles();
    $watcher = resolve(VaultWatcher::class);
    $watcher->start($vault);
    $watcher->markReady($vault);

    $listener = resolve(HandleVaultWatcherExit::class);

    $listener->handle(new ProcessExited('something-else', 0));

    expect($watcher->isWatching($vault))->toBeTrue();

    $listener->handle(new ProcessExited(VaultWatcher::ALIAS, 1));
    $listener->handle(new ProcessExited(VaultWatcher::ALIAS, 1));

    expect($watcher->isWatching($vault))->toBeFalse();
    Event::assertDispatchedTimes(VaultWatchStatusChanged::class, 1);
    Event::assertDispatched(VaultWatchStatusChanged::class, fn ($event): bool => $event->vaultId === $vault->id && ! $event->watching);
});
