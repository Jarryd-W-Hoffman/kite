<?php

declare(strict_types=1);

use Illuminate\Support\Sleep;
use Symfony\Component\Process\Process;

/**
 * Exercises the real Node watcher script against a temporary vault.
 */
function startWatcher(string $path): Process
{
    $process = new Process(['node', resource_path('watcher/vault-watcher.mjs'), $path, 'md,png']);
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

    mkdir($vault->path.'/Sub');
    mkdir($vault->path.'/.git');
    file_put_contents($vault->path.'/Sub/New.md', '# New');
    file_put_contents($vault->path.'/pic.png', 'png');
    file_put_contents($vault->path.'/notes.txt', 'ignored');
    file_put_contents($vault->path.'/.git/HEAD.md', 'ignored');

    $lines = watcherLines($process, fn (array $lines): bool => count(array_merge(...array_column($lines, 'paths'))) >= 2);

    $process->stop(1, SIGTERM);

    $paths = array_unique(array_merge(...array_column(array_slice($lines, 1), 'paths')));

    expect($paths)->toContain('Sub/New.md', 'pic.png')
        ->not->toContain('notes.txt', '.git/HEAD.md');
})->skip(fn (): bool => new Process(['node', '--version'])->run() !== 0, 'Node is not installed.');

it('exits with a usage error without a vault path', function (): void {
    $process = new Process(['node', resource_path('watcher/vault-watcher.mjs')]);
    $process->run();

    expect($process->getExitCode())->toBe(64)
        ->and($process->getErrorOutput())->toContain('Usage');
})->skip(fn (): bool => new Process(['node', '--version'])->run() !== 0, 'Node is not installed.');
