<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\QuickCaptureRequested;
use App\Listeners\OpenQuickCapture;
use App\Models\ColourGroup;
use App\Models\Note;
use App\Models\Vault;
use App\Services\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use PDO;
use Pdo\Sqlite;
use PDOException;
use WeakMap;

final class AppServiceProvider extends ServiceProvider
{
    /** @var WeakMap<PDO, true>|null */
    private ?WeakMap $registered = null;

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->app->booted(fn () => resolve(Settings::class)->applyToConfig());

        // Registered explicitly: the listener ignores its payload, so discovery can't infer the event.
        Event::listen(QuickCaptureRequested::class, OpenQuickCapture::class);

        // Changes the workspace sidebar shows move the vault to a new tree revision.
        Note::updated(function (Note $note): void {
            if ($note->wasChanged(Note::TREE_FIELDS)) {
                Vault::markTreeChanged($note->vault_id);
            }
        });
        Note::deleted(fn (Note $note) => Vault::markTreeChanged($note->vault_id));
        ColourGroup::saved(fn (ColourGroup $group) => Vault::markTreeChanged($group->vault_id));
        ColourGroup::deleted(fn (ColourGroup $group) => Vault::markTreeChanged($group->vault_id));

        // Guessing a vault's passphrase or recovery key, per vault.
        RateLimiter::for('vault-passphrase', fn (Request $request): Limit => Limit::perMinute(10)
            ->by('vault-passphrase:'.$request->route()?->originalParameter('vault')));

        // Added just before a connection's first query, so configuring one never opens it (the
        // database may not exist yet, e.g. while packages are discovered).
        Event::listen(ConnectionEstablished::class, fn (ConnectionEstablished $event) => $this->registerSqlFunctionsOnUse($event->connection));

        foreach (DB::getConnections() as $connection) {
            $this->registerSqlFunctionsOnUse($connection);
        }
    }

    private function registerSqlFunctionsOnUse(Connection $connection): void
    {
        $connection->beforeExecuting(function (string $query, array $bindings, Connection $connection): void {
            try {
                $pdo = $connection->getPdo();
            } catch (InvalidArgumentException|PDOException) {
                // No database to connect to: the query itself fails as it always would.
                return;
            }

            $this->registerSqlFunctions($pdo);
        });
    }

    /**
     * `kite_lower()` lowercases text the way PHP does: SQLite's own `lower()` only knows ASCII,
     * and searches ignore case in every script. Each database handle gets it once.
     */
    private function registerSqlFunctions(PDO $pdo): void
    {
        $this->registered ??= new WeakMap;

        if ($pdo instanceof Sqlite && ! isset($this->registered[$pdo])) {
            $this->registered[$pdo] = true;

            $pdo->createFunction(
                'kite_lower',
                fn (mixed $value): ?string => is_scalar($value) ? mb_strtolower((string) $value) : null,
                1,
                Sqlite::DETERMINISTIC,
            );
        }
    }
}
