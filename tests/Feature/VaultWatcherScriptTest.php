<?php

declare(strict_types=1);

use Illuminate\Support\Sleep;
use Symfony\Component\Process\Process;

/**
 * Exercises the real Node watcher script against a temporary vault.
 */
function startWatcher(string $path): Process
{
    $process = new Process(['node', resource_path('watcher/vault-watcher.mjs'), $path, 'md,png', 'Archive,node_modules']);
    $process->setTimeout(15);
    $process->start();

    return $process;
}

/**
 * @return list<array<string, mixed>>
 */
function watcherLines(Process $process, Closure $until, float $seconds = 5.0): array
{
    $deadline = microtime(true) + $seconds;
    $lines = [];

    while (microtime(true) < $deadline) {
        $lines = array_values(array_filter(array_map(
            fn (string $line): mixed => json_decode($line, true),
            explode("\n", mb_trim($process->getOutput())),
        ), is_array(...)));

        if ($until($lines) || ! $process->isRunning()) {
            break;
        }

        Sleep::usleep(50_000);
    }

    return $lines;
}

it('reports debounced markdown and attachment changes, ignoring hidden and unrelated files', function (): void {
    $vault = vaultWithFiles(['Existing.md' => 'x']);
    $process = startWatcher($vault->path);

    $ready = watcherLines($process, fn (array $lines): bool => $lines !== []);

    if (! $process->isRunning() && str_contains($process->getErrorOutput(), 'ENOSPC')) {
        $this->markTestSkipped('The OS has no inotify watches left.');
    }

    expect($ready[0] ?? null)->toBe(['root' => $vault->path, 'ready' => true]);

    // macOS starts delivering file events a moment after the watcher reports ready, and drops
    // changes made before then, so touch a probe until it comes through.
    $reported = fn (array $lines): array => array_merge(...array_column($lines, 'paths'));

    for ($attempt = 0; $attempt < 25 && ! in_array('Probe.md', $reported(watcherLines($process, fn (array $lines): bool => in_array('Probe.md', $reported($lines), true), 0.4)), true); $attempt++) {
        file_put_contents($vault->path.'/Probe.md', (string) $attempt);
    }

    expect($reported(watcherLines($process, fn (array $lines): bool => true)))->toContain('Probe.md');

    mkdir($vault->path.'/Sub');
    mkdir($vault->path.'/.git');
    file_put_contents($vault->path.'/Sub/New.md', '# New');
    file_put_contents($vault->path.'/pic.png', 'png');
    file_put_contents($vault->path.'/notes.txt', 'ignored');
    file_put_contents($vault->path.'/.git/HEAD.md', 'ignored');
    mkdir($vault->path.'/Archive');
    file_put_contents($vault->path.'/Archive/Old.md', 'ignored');

    // On macOS a file made in a brand-new folder may be reported only as that folder; either
    // way the app re-indexes the vault, so the folder counts. Events can arrive in any order.
    $seen = fn (array $paths): bool => in_array('pic.png', $paths, true) && array_intersect(['Sub/New.md', 'Sub'], $paths) !== [];
    $lines = watcherLines($process, fn (array $lines): bool => $seen($reported($lines)));

    $process->stop(1);

    $paths = array_values(array_unique($reported($lines)));

    expect($seen($paths))->toBeTrue('Reported: '.implode(', ', $paths))
        ->and($paths)->not->toContain('notes.txt', '.git/HEAD.md', 'Archive/Old.md');
})->skip(fn (): bool => new Process(['node', '--version'])->run() !== 0, 'Node is not installed.');

it('exits with a usage error without a vault path', function (): void {
    $process = new Process(['node', resource_path('watcher/vault-watcher.mjs')]);
    $process->run();

    expect($process->getExitCode())->toBe(64)
        ->and($process->getErrorOutput())->toContain('Usage');
})->skip(fn (): bool => new Process(['node', '--version'])->run() !== 0, 'Node is not installed.');
