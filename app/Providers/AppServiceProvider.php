<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\MenuCommandSelected;
use App\Events\QuickCaptureRequested;
use App\Listeners\OpenQuickCapture;
use App\Listeners\ShowMainWindowForWelcome;
use App\Models\ColourGroup;
use App\Models\Note;
use App\Models\Vault;
use App\Services\Settings;
use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Native\Desktop\Events\LivewireDispatcher;
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
        // Listening before any provider boots means every connection is covered. Functions are
        // added just before a connection's first query, so configuring one never opens it (the
        // database may not exist yet, e.g. while packages are discovered).
        Event::listen(ConnectionEstablished::class, fn (ConnectionEstablished $event) => $this->registerSqlFunctionsOnUse($event->connection));

        // A failed query's error quotes its values, which can be a note's text, and errors reach
        // the logs, diagnostics and crash reports. A resolver also covers the connection NativePHP
        // configures for itself.
        Connection::resolverFor('sqlite', fn (PDO|Closure $pdo, string $database, string $prefix, array $config): SQLiteConnection => new SQLiteConnection(
            $pdo, $database, $prefix, [...$config, 'mask_bindings_in_exception_messages' => true],
        ));

        // NativePHP adds an inline script to every page to pass its events to Livewire. Kite has no
        // Livewire (its interface listens through `window.Native`), and the script has no nonce, so
        // the content security policy would refuse it with an error on every page.
        $this->app->bind(LivewireDispatcher::class, fn (): LivewireDispatcher => new class extends LivewireDispatcher
        {
            public function register(): void
            {
                //
            }
        });
    }

    public function boot(): void
    {
        $this->app->booted(fn () => resolve(Settings::class)->applyToConfig());

        // Registered explicitly: the listener ignores its payload, so discovery can't infer the event.
        Event::listen(QuickCaptureRequested::class, OpenQuickCapture::class);
        Event::listen(MenuCommandSelected::class, ShowMainWindowForWelcome::class);

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
