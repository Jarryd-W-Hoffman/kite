<?php

declare(strict_types=1);

namespace App\Services\Flashcards;

use App\Models\Vault;
use App\Services\VaultFilesystem;
use Illuminate\Support\Facades\File;

/**
 * Cards' review schedules, kept in the vault as `.kite/flashcards.json` so they travel with it
 * (sync, backups, another machine) without Kite ever writing into the notes.
 *
 * @phpstan-import-type CardState from Scheduler
 */
final readonly class ScheduleStore
{
    private const string FILE = '.kite/flashcards.json';

    public function __construct(private VaultFilesystem $files)
    {
        //
    }

    /**
     * @return array<string, CardState>
     */
    public function all(Vault $vault): array
    {
        $file = $this->file($vault);
        $data = File::exists($file) ? json_decode(File::get($file), true) : null;
        $cards = is_array($data) && is_array($data['cards'] ?? null) ? $data['cards'] : [];
        $states = [];

        foreach ($cards as $key => $state) {
            if (is_string($key) && $this->isState($state)) {
                $states[$key] = $state;
            }
        }

        return $states;
    }

    /**
     * @param  CardState  $state
     */
    public function put(Vault $vault, string $key, array $state): void
    {
        $states = $this->all($vault);
        $states[$key] = $state;
        ksort($states);

        $file = $this->file($vault);
        $temporary = $file.'.'.bin2hex(random_bytes(4)).'.tmp';

        File::ensureDirectoryExists(dirname($file));
        File::put($temporary, json_encode(['version' => 1, 'cards' => $states], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)."\n");
        File::move($temporary, $file);
    }

    /**
     * @phpstan-assert-if-true CardState $state
     */
    private function isState(mixed $state): bool
    {
        return is_array($state)
            && is_string($state['due'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $state['due']) === 1
            && is_int($state['interval'] ?? null) && is_int($state['reps'] ?? null) && is_int($state['lapses'] ?? null)
            && (is_float($state['ease'] ?? null) || is_int($state['ease'] ?? null))
            && is_string($state['introduced'] ?? null) && is_string($state['reviewed'] ?? null);
    }

    private function file(Vault $vault): string
    {
        return $this->files->contained($vault, $vault->path.DIRECTORY_SEPARATOR.self::FILE);
    }
}
