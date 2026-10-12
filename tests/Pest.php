<?php

declare(strict_types=1);

use App\Models\Vault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        Str::createRandomStringsNormally();
        Str::createUuidsNormally();
        Http::preventStrayRequests();
        Process::preventStrayProcesses();
        Sleep::fake();

        $this->freezeTime();
    })
    ->afterEach(function (): void {
        foreach (TemporaryVaults::$paths as $path) {
            File::deleteDirectory($path);
        }

        TemporaryVaults::$paths = [];
    })
    ->in('Browser', 'Feature', 'Unit');

expect()->extend('toBeOne', fn () => $this->toBe(1));

final class TemporaryVaults
{
    /**
     * @var list<string>
     */
    public static array $paths = [];
}

/**
 * Create a vault backed by a real temporary folder containing the given markdown files.
 *
 * @param  array<string, string>  $files
 */
function vaultWithFiles(array $files = []): Vault
{
    $path = storage_path('framework/testing/vaults/'.Str::uuid());

    File::ensureDirectoryExists($path);

    TemporaryVaults::$paths[] = $path;

    foreach ($files as $relativePath => $contents) {
        File::ensureDirectoryExists(dirname($path.'/'.$relativePath));
        File::put($path.'/'.$relativePath, $contents);
    }

    return Vault::factory()->create([
        'name' => basename($path),
        'path' => realpath($path),
    ]);
}

/**
 * Write a file into a vault and bump its modification time so the indexer notices the change.
 */
function writeVaultFile(Vault $vault, string $relativePath, string $contents): void
{
    $absolutePath = $vault->path.'/'.$relativePath;

    File::ensureDirectoryExists(dirname($absolutePath));
    File::put($absolutePath, $contents);

    touch($absolutePath, time() + random_int(10, 1000));
    clearstatcache();
}
