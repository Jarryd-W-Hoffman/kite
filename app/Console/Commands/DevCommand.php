<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs the desktop app and Vite together for development, and stops everything when either one
 * ends or the command is interrupted. Each runs in its own process group, so Electron and the
 * helpers it starts are stopped too instead of being left running without Vite.
 */
#[Description('Run the Kite desktop app and the Vite dev server together')]
#[Signature('kite:dev')]
final class DevCommand extends Command
{
    /** How long a process group gets to exit after SIGTERM before it is killed. */
    private const int GRACE_SECONDS = 3;

    /**
     * Starts a command as the leader of a new process group. `setsid` would do, but macOS doesn't
     * have it, and PHP with posix and pcntl is always at hand.
     */
    private const string NEW_GROUP = 'posix_setsid(); pcntl_exec($argv[1], array_slice($argv, 2));';

    private bool $interrupted = false;

    public function handle(): int
    {
        /** @var array<string, list<string>> $commands */
        $commands = config('kite.dev.commands');
        /** @var array<string, string> $env */
        $env = config('kite.dev.env', []);
        $processes = [];

        foreach ($commands as $name => $command) {
            $process = new Process([PHP_BINARY, '-r', self::NEW_GROUP, '--', $this->executable($command[0]), ...array_slice($command, 1)], base_path(), $env, null, null);
            $process->start(function (string $type, string $output) use ($name): void {
                foreach (explode("\n", mb_rtrim($output, "\n")) as $line) {
                    $this->line("<fg=gray>[{$name}]</> {$line}");
                }
            });
            $processes[$name] = $process;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGINT, fn (): bool => $this->interrupted = true);
        pcntl_signal(SIGTERM, fn (): bool => $this->interrupted = true);

        while (! $this->interrupted && array_all($processes, fn (Process $process): bool => $process->isRunning())) {
            Sleep::usleep(100_000);
        }

        $finished = array_filter($processes, fn (Process $process): bool => ! $process->isRunning());

        foreach ($processes as $process) {
            $this->stopGroup($process);
        }

        $this->components->info('Stopped '.implode(' and ', array_keys($processes)).'.');

        $first = reset($finished);

        return $this->interrupted || $first === false ? self::SUCCESS : (int) $first->getExitCode();
    }

    /**
     * The full path of a program, as `pcntl_exec()` doesn't search the PATH.
     */
    private function executable(string $program): string
    {
        return str_contains($program, DIRECTORY_SEPARATOR) ? $program : new ExecutableFinder()->find($program) ?? $program;
    }

    /**
     * Signal the whole process group (the process leads its own), then kill what's left.
     */
    private function stopGroup(Process $process): void
    {
        $group = $process->getPid();

        if ($group !== null) {
            posix_kill(-$group, SIGTERM);

            // isRunning() reaps the leader, so an empty group stops answering the signal-0 probe.
            for ($waited = 0; $waited < self::GRACE_SECONDS * 10 && ($process->isRunning() || posix_kill(-$group, 0)); $waited++) {
                Sleep::usleep(100_000);
            }

            posix_kill(-$group, SIGKILL);
        }

        $process->stop(0);
    }
}
