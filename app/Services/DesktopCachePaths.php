<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Keeps Laravel's cache files in the user's data folder when Kite runs as a desktop app.
 *
 * NativePHP rebuilds them on every launch with `artisan optimize`, but only moves them out of the
 * app's own folder for its bundled builds. Kite is installed read-only on Linux (`/opt/Kite`), so
 * the cache couldn't be written there, and on macOS writing inside the app bundle would break
 * its signature.
 */
final readonly class DesktopCachePaths
{
    /**
     * Laravel's cache files, by the environment variable that moves each one.
     */
    private const array FILES = [
        'APP_SERVICES_CACHE' => 'services.php',
        'APP_PACKAGES_CACHE' => 'packages.php',
        'APP_CONFIG_CACHE' => 'config.php',
        'APP_ROUTES_CACHE' => 'routes-v7.php',
        'APP_EVENTS_CACHE' => 'events.php',
    ];

    /**
     * Point any cache file that isn't already placed at NativePHP's user data folder. Runs before
     * Laravel boots, as that's when it reads where the cache files are.
     */
    public function apply(): void
    {
        $userData = getenv('NATIVEPHP_USER_DATA_PATH');

        if (! is_string($userData) || $userData === '') {
            return;
        }

        $folder = implode(DIRECTORY_SEPARATOR, [mb_rtrim($userData, '/\\'), 'bootstrap', 'cache']);

        foreach (self::FILES as $key => $file) {
            if (getenv($key) !== false) {
                continue;
            }

            putenv("{$key}={$folder}".DIRECTORY_SEPARATOR.$file);
        }
    }
}
