<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Services\SystemOpener;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Native\Desktop\Facades\Shell;

beforeEach(function (): void {
    $this->storage = storage_path('framework/testing/diagnostics-'.Str::uuid());
    File::ensureDirectoryExists($this->storage.'/logs');
    app()->useStoragePath($this->storage);
    TemporaryVaults::$paths[] = $this->storage;
    $this->home = getenv('HOME');
});

afterEach(function (): void {
    putenv($this->home === false ? 'HOME' : "HOME={$this->home}");
});

it('reports versions, vaults, settings and recent errors with the home folder shortened', function (): void {
    $vault = vaultWithFiles(['A.md' => '# A', 'B.md' => '# B']);
    resolve(IndexVault::class)->handle($vault);
    $home = dirname($vault->path, 2);
    putenv("HOME={$home}");

    File::put($this->storage.'/logs/laravel-2026-10-14.log', "[2026-10-14 09:00:00] production.ERROR: Old failure\n");
    File::put($this->storage.'/logs/laravel-2026-10-15.log', implode("\n", [
        '[2026-10-15 09:00:00] production.INFO: Not an error',
        "[2026-10-15 09:01:00] production.ERROR: Could not read {$vault->path}/A.md {\"exception\":\"...\"}",
        '#0 /stack/trace/line.php(10)',
        '[2026-10-15 09:02:00] production.CRITICAL: '.str_repeat('x', 400),
    ])."\n");
    touch($this->storage.'/logs/laravel-2026-10-14.log', now()->subDay()->getTimestamp());

    $report = (string) $this->getJson(route('diagnostics.show'))->assertOk()->json('report');

    expect($report)->toContain('## Kite diagnostics')
        ->toContain('- Kite '.config('nativephp.version').' (browser)')
        ->toContain('PHP '.PHP_VERSION)
        ->toContain('- '.osPath('~/'.basename(dirname($vault->path)).'/'.basename($vault->path)).': 2 notes')
        ->toContain('- daily_folder: "Daily"')
        ->toContain('2026-10-14 09:00:00 ERROR: Old failure')
        ->toContain('2026-10-15 09:01:00 ERROR: Could not read ~'.DIRECTORY_SEPARATOR)
        ->toContain('2026-10-15 09:02:00 CRITICAL: '.str_repeat('x', 299).'…')
        ->not->toContain($home)
        ->not->toContain('Not an error')
        ->not->toContain('/stack/trace')
        ->and(mb_strpos($report, 'Old failure'))->toBeLessThan(mb_strpos($report, 'Could not read'));
});

it('says when there are no vaults or errors', function (): void {
    expect($this->getJson(route('diagnostics.show'))->json('report'))
        ->toContain("### Vaults\n\n- none")
        ->toContain("### Recent errors\n\n- none");
});

it('shows the log files only in the desktop app', function (): void {
    $this->post(route('diagnostics-logs.store'))->assertNotFound();

    config(['nativephp-internal.running' => true, 'nativephp-internal.secret' => 'secret']);
    app()->instance(SystemOpener::class, new SystemOpener('Darwin'));
    Shell::shouldReceive('openFile')->once()->with(storage_path('logs'))->andReturn('');

    $this->withHeader('X-NativePHP-Secret', 'secret')->post(route('diagnostics-logs.store'))->assertNoContent();
});
