<?php

declare(strict_types=1);

use App\Services\DesktopCachePaths;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

const DESKTOP_CACHE_KEYS = ['NATIVEPHP_USER_DATA_PATH', 'APP_SERVICES_CACHE', 'APP_PACKAGES_CACHE', 'APP_CONFIG_CACHE', 'APP_ROUTES_CACHE', 'APP_EVENTS_CACHE'];

beforeEach(function (): void {
    $this->saved = array_map(getenv(...), array_combine(DESKTOP_CACHE_KEYS, DESKTOP_CACHE_KEYS));

    foreach (DESKTOP_CACHE_KEYS as $key) {
        putenv($key);
    }
});

afterEach(function (): void {
    foreach ($this->saved as $key => $value) {
        putenv($value === false ? $key : "{$key}={$value}");
    }
});

it('keeps the cache files in the user data folder in the desktop app', function (): void {
    putenv('NATIVEPHP_USER_DATA_PATH=/home/someone/.config/kite/');

    new DesktopCachePaths()->apply();

    // Laravel looks these up when it boots, falling back to getenv().
    expect(app()->getCachedConfigPath())->toBe(implode(DIRECTORY_SEPARATOR, ['/home/someone/.config/kite', 'bootstrap', 'cache', 'config.php']));

    $folder = implode(DIRECTORY_SEPARATOR, ['/home/someone/.config/kite', 'bootstrap', 'cache']);

    expect(getenv('APP_CONFIG_CACHE'))->toBe($folder.DIRECTORY_SEPARATOR.'config.php')
        ->and(getenv('APP_ROUTES_CACHE'))->toBe($folder.DIRECTORY_SEPARATOR.'routes-v7.php')
        ->and(getenv('APP_SERVICES_CACHE'))->toBe($folder.DIRECTORY_SEPARATOR.'services.php')
        ->and(getenv('APP_PACKAGES_CACHE'))->toBe($folder.DIRECTORY_SEPARATOR.'packages.php')
        ->and(getenv('APP_EVENTS_CACHE'))->toBe($folder.DIRECTORY_SEPARATOR.'events.php');
});

it('leaves a cache file that is already placed', function (): void {
    putenv('NATIVEPHP_USER_DATA_PATH=/home/someone/.config/kite');
    putenv('APP_CONFIG_CACHE=/elsewhere/config.php');

    new DesktopCachePaths()->apply();

    expect(getenv('APP_CONFIG_CACHE'))->toBe('/elsewhere/config.php')
        ->and(getenv('APP_ROUTES_CACHE'))->toEndWith('routes-v7.php');
});

it('changes nothing outside the desktop app', function (): void {
    new DesktopCachePaths()->apply();

    expect(getenv('APP_CONFIG_CACHE'))->toBeFalse();
});

it('caches the config in the user data folder, not the app folder', function (): void {
    $userData = storage_path('framework/testing/desktop-cache-'.Str::uuid());
    File::ensureDirectoryExists($userData.'/bootstrap/cache');

    $process = new Process([PHP_BINARY, 'artisan', 'config:cache'], base_path(), ['NATIVEPHP_USER_DATA_PATH' => $userData, 'APP_CONFIG_CACHE' => false]);
    $process->mustRun();

    expect(File::exists($userData.'/bootstrap/cache/config.php'))->toBeTrue()
        ->and(File::exists(base_path('bootstrap/cache/config.php')))->toBeFalse();

    File::deleteDirectory($userData);
})->skipOnWindows();
