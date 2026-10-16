<?php

declare(strict_types=1);

use Illuminate\Support\Sleep;

afterEach(function (): void {
    if (function_exists('pcntl_signal')) {
        pcntl_signal(SIGINT, SIG_DFL);
        pcntl_signal(SIGTERM, SIG_DFL);
    }
});

it('stops the other process group, children included, when one process ends', function (): void {
    $marker = tempnam(sys_get_temp_dir(), 'kite-dev');

    config(['kite.dev.commands' => [
        'slow' => ['sh', '-c', "sleep 60 & echo \$! > {$marker}; wait"],
        'quick' => ['sh', '-c', 'sleep 0.5; echo hello; exit 3'],
    ]]);

    $this->artisan('kite:dev')
        ->expectsOutputToContain('[quick] hello')
        ->expectsOutputToContain('Stopped slow and quick.')
        ->assertExitCode(3);

    $child = (int) file_get_contents($marker);
    unlink($marker);

    // A killed child can linger as a zombie until whichever process inherited it reaps it; that
    // still counts as stopped.
    $running = fn (): bool => posix_kill($child, 0) && ! str_starts_with(mb_trim((string) shell_exec("ps -o stat= -p {$child}")), 'Z');

    for ($waited = 0; $waited < 50 && $running(); $waited++) {
        Sleep::usleep(100_000);
    }

    expect($child)->toBeGreaterThan(0)
        ->and($running())->toBeFalse();
})->skipOnWindows();

it('stops everything when interrupted', function (): void {
    config(['kite.dev.commands' => [
        'app' => ['sleep', '60'],
        'signal' => ['sh', '-c', 'sleep 0.5; kill -INT '.getmypid().'; sleep 60'],
    ]]);

    $this->artisan('kite:dev')
        ->expectsOutputToContain('Stopped app and signal.')
        ->assertExitCode(0);
})->skipOnWindows();
