<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Vault;
use App\Services\IndexingProgress;
use App\Services\Settings;
use App\Services\VaultWatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

final readonly class CollectDiagnostics
{
    private const int ERRORS = 10;

    /** How much of the end of each log file is searched for errors. */
    private const int LOG_TAIL_BYTES = 512 * 1024;

    public function __construct(
        private Settings $settings,
        private IndexingProgress $progress,
        private VaultWatcher $watcher,
    ) {
        //
    }

    /**
     * A plain-text report to paste into a bug report: versions, settings, vault sizes and recent
     * errors. It is only ever shown to the user to copy; nothing is sent anywhere. The home
     * folder is written as `~`, so paths don't carry the user's account name.
     */
    public function handle(): string
    {
        $vaults = Vault::query()->latest('opened_at')->get()->map(fn (Vault $vault): string => sprintf(
            '- %s: %d notes, indexed %s, %s%s',
            $vault->path,
            $vault->notes()->count(),
            $vault->indexed_at?->toIso8601String() ?? 'never',
            $this->watcher->isWatching($vault) ? 'watching' : 'not watching',
            $this->progress->get($vault) === null ? '' : ', indexing now',
        ))->all();

        $settings = array_map(fn (mixed $value): string => (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $this->settings->all());

        $lines = [
            '## Kite diagnostics',
            '',
            '- Kite '.config()->string('nativephp.version', 'unknown').(config('nativephp-internal.running') ? ' (desktop)' : ' (browser)'),
            '- Laravel '.app()->version().', PHP '.PHP_VERSION.', SQLite '.$this->sqliteVersion(),
            '- '.php_uname('s').' '.php_uname('r').' ('.php_uname('m').')',
            '- Database '.$this->megabytes($this->databaseBytes()).', memory limit '.ini_get('memory_limit'),
            '- Generated '.now()->toIso8601String(),
            '',
            '### Vaults',
            '',
            ...($vaults === [] ? ['- none'] : $vaults),
            '',
            '### Settings',
            '',
            ...array_map(fn (string $key, string $value): string => "- {$key}: {$value}", array_keys($settings), $settings),
            '',
            '### Recent errors',
            '',
            ...(($errors = $this->recentErrors()) === [] ? ['- none'] : array_map(fn (string $error): string => '- '.$error, $errors)),
        ];

        return $this->withoutHome(implode("\n", $lines))."\n";
    }

    /**
     * The newest error-level log entries, first line only and shortened.
     *
     * @return list<string>
     */
    private function recentErrors(): array
    {
        $files = glob(storage_path('logs/*.log')) ?: [];

        usort($files, fn (string $a, string $b): int => File::lastModified($a) <=> File::lastModified($b));

        $errors = [];

        foreach ($files as $file) {
            $handle = fopen($file, 'r');

            if ($handle === false) {
                continue;
            }

            fseek($handle, max(0, (int) filesize($file) - self::LOG_TAIL_BYTES));
            $tail = (string) stream_get_contents($handle);
            fclose($handle);

            preg_match_all('/^\[([^\]]+)\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)$/m', $tail, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $errors[] = "{$match[1]} {$match[2]}: ".mb_strimwidth(mb_trim($match[3]), 0, 300, '…');
            }
        }

        return array_slice($errors, -self::ERRORS);
    }

    private function sqliteVersion(): string
    {
        $row = DB::selectOne('select sqlite_version() as version');

        return is_object($row) && isset($row->version) && is_string($row->version) ? $row->version : 'unknown';
    }

    private function databaseBytes(): int
    {
        $database = config('database.connections.sqlite.database');

        return is_string($database) && File::exists($database) ? File::size($database) : 0;
    }

    private function megabytes(int $bytes): string
    {
        return number_format($bytes / 1_048_576, 1).' MB';
    }

    private function withoutHome(string $text): string
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE');

        return is_string($home) && mb_strlen($home) > 1 ? str_replace(mb_rtrim($home, '/\\'), '~', $text) : $text;
    }
}
