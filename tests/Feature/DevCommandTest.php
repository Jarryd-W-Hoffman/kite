<?php

declare(strict_types=1);

afterEach(function (): void {
    pcntl_signal(SIGINT, SIG_DFL);
    pcntl_signal(SIGTERM, SIG_DFL);
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

    expect($child)->toBeGreaterThan(0)
        ->and(posix_kill($child, 0))->toBeFalse();
});

it('stops everything when interrupted', function (): void {
    config(['kite.dev.commands' => [
        'app' => ['sleep', '60'],
        'signal' => ['sh', '-c', 'sleep 0.5; kill -INT '.getmypid().'; sleep 60'],
    ]]);

    $this->artisan('kite:dev')
        ->expectsOutputToContain('Stopped app and signal.')
        ->assertExitCode(0);
});
