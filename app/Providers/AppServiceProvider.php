<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\QuickCaptureRequested;
use App\Listeners\OpenQuickCapture;
use App\Services\Settings;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Pdo\Sqlite;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->app->booted(fn () => resolve(Settings::class)->applyToConfig());

        // Registered explicitly: the listener ignores its payload, so discovery can't infer the event.
        Event::listen(QuickCaptureRequested::class, OpenQuickCapture::class);

        Event::listen(ConnectionEstablished::class, fn (ConnectionEstablished $event) => $this->registerSqlFunctions($event->connection));

        foreach (DB::getConnections() as $connection) {
            $this->registerSqlFunctions($connection);
        }
    }

    /**
     * `kite_lower()` lowercases text the way PHP does: SQLite's own `lower()` only knows ASCII,
     * and searches ignore case in every script.
     */
    private function registerSqlFunctions(Connection $connection): void
    {
        $pdo = $connection->getPdo();

        if ($pdo instanceof Sqlite) {
            $pdo->createFunction(
                'kite_lower',
                fn (mixed $value): ?string => is_scalar($value) ? mb_strtolower((string) $value) : null,
                1,
                Sqlite::DETERMINISTIC,
            );
        }
    }
}
