<?php

declare(strict_types=1);

use App\Models\Vault;
use App\Services\CrashReports;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\SentrySdk;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;
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

// Benchmarks set up their own database file, to time work as the app does it.
pest()->extend(TestCase::class)->in('Benchmark');

expect()->extend('toBeOne', fn () => $this->toBe(1));

final class TemporaryVaults
{
    /**
     * @var list<string>
     */
    public static array $paths = [];
}

/**
 * A path written with `/` as the app builds it on this platform, with its own directory separator.
 */
function osPath(string $path): string
{
    return str_replace('/', DIRECTORY_SEPARATOR, $path);
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

/**
 * Visit a page with notes shown in split view (editor beside preview), which most browser tests
 * read rendered output from. New users start in the editor alone.
 */
function visitSplit(string $url): mixed
{
    $page = visit($url);

    // Remembered for later pages too; switches a note page already open without reloading it.
    $page->script("localStorage.setItem('kite:view-mode', 'split'); document.querySelector('[aria-label=Split]:not([data-state=on])')?.click()");

    return $page;
}

/**
 * Point Sentry at a transport that keeps events instead of sending them, with Kite's own options.
 *
 * @return ArrayObject<int, Event>
 */
function fakeSentry(): ArrayObject
{
    $sent = new ArrayObject();

    $transport = new readonly class($sent) implements TransportInterface
    {
        /**
         * @param  ArrayObject<int, Event>  $sent
         */
        public function __construct(private ArrayObject $sent) {}

        public function send(Event $event): Result
        {
            $this->sent->append($event);

            return new Result(ResultStatus::success(), $event);
        }

        public function close(?int $timeout = null): Result
        {
            return new Result(ResultStatus::success());
        }
    };

    SentrySdk::getCurrentHub()->bindClient(
        ClientBuilder::create([
            'dsn' => 'https://public@sentry.example/1',
            'before_send' => [CrashReports::class, 'beforeSend'],
        ])->setTransport($transport)->getClient(),
    );

    return $sent;
}

function turnOnCrashReports(): void
{
    app()->detectEnvironment(fn (): string => 'production');
    config(['sentry.dsn' => 'https://public@sentry.example/1']);
    resolve(Settings::class)->update(['crash_reports' => true]);
}
