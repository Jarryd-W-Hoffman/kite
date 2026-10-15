<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Vault;
use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LogicException;
use Native\Desktop\Facades\Shell;
use RuntimeException;

/**
 * Comments live beside the vault's notes, one JSON file per note under `.kite/comments`, mirroring
 * the note's path (`Work/Plan.md` → `.kite/comments/Work/Plan.md.json`). They travel with the
 * vault wherever it is synced, and dot-folders are never indexed as notes.
 *
 * @phpstan-type Comment array{id: string, body: string, quote: string, prefix: string, suffix: string, resolved: bool, created_at: string, updated_at: string}
 */
final readonly class CommentStore
{
    public const string FOLDER = '.kite/comments';

    private const int FORMAT = 1;

    public function __construct(
        private VaultFilesystem $files,
        private KiteCache $cache,
    ) {
        //
    }

    /**
     * Absolute path of a note's comment file (or, for a folder, of its comment folder).
     */
    public function path(Vault $vault, string $notePath, string $extension = '.json'): string
    {
        return $this->files->contained($vault, $vault->path.DIRECTORY_SEPARATOR
            .str_replace('/', DIRECTORY_SEPARATOR, self::FOLDER.'/'.$this->files->normalise($notePath).$extension));
    }

    /**
     * @return list<Comment>
     */
    public function all(Vault $vault, string $notePath): array
    {
        return array_values(array_filter(array_map(
            $this->normalise(...),
            $this->read($vault, $notePath),
        )));
    }

    /**
     * @param  array{body: string, quote: string, prefix: string, suffix: string}  $comment
     * @return Comment
     */
    public function add(Vault $vault, string $notePath, array $comment): array
    {
        $now = now()->toIso8601String();
        $entry = [
            'id' => Str::lower((string) Str::ulid()),
            ...$comment,
            'resolved' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $this->mutate($vault, $notePath, fn (array $comments): array => [...$comments, $entry]);

        /** @var Comment $entry */
        return $entry;
    }

    /**
     * @param  array{body?: string, quote?: string, prefix?: string, suffix?: string, resolved?: bool}  $changes
     * @return Comment|null
     */
    public function update(Vault $vault, string $notePath, string $id, array $changes): ?array
    {
        $updated = null;

        $this->mutate($vault, $notePath, function (array $comments) use ($id, $changes, &$updated): array {
            foreach ($comments as $index => $comment) {
                if (is_array($comment) && ($comment['id'] ?? null) === $id) {
                    $comments[$index] = [...$comment, ...$changes, 'updated_at' => now()->toIso8601String()];
                    $updated = $this->normalise($comments[$index]);
                }
            }

            return $comments;
        });

        return $updated;
    }

    public function delete(Vault $vault, string $notePath, string $id): bool
    {
        $found = false;

        $this->mutate($vault, $notePath, function (array $comments) use ($id, &$found): array {
            $kept = array_values(array_filter($comments, fn (mixed $comment): bool => ! is_array($comment) || ($comment['id'] ?? null) !== $id));
            $found = count($kept) !== count($comments);

            return $kept;
        });

        return $found;
    }

    /**
     * Follow a note (or a whole folder of notes) to its new path.
     */
    public function move(Vault $vault, string $from, string $to, bool $folder = false): void
    {
        $extension = $folder ? '' : '.json';
        $source = $this->path($vault, $from, $extension);

        if (! File::exists($source)) {
            return;
        }

        $destination = $this->path($vault, $to, $extension);

        File::ensureDirectoryExists(dirname($destination));
        File::move($source, $destination);
        $this->removeEmptyFolders($vault, dirname($source));
    }

    /**
     * Send a note's comments (or a folder's) to the trash along with it.
     */
    public function trash(Vault $vault, string $path, bool $folder = false): void
    {
        $target = $this->path($vault, $path, $folder ? '' : '.json');

        if (! File::exists($target)) {
            return;
        }

        if ($this->files->usesTrash()) {
            Shell::trashFile($target);
        } elseif ($folder) {
            File::deleteDirectory($target);
        } else {
            File::delete($target);
        }

        $this->removeEmptyFolders($vault, dirname($target));
    }

    /**
     * @param  Closure(list<mixed>): list<mixed>  $change
     */
    private function mutate(Vault $vault, string $notePath, Closure $change): void
    {
        $file = $this->path($vault, $notePath);

        $store = $this->cache->store()->getStore();

        throw_unless($store instanceof LockProvider, LogicException::class, 'The Kite cache store must support atomic locks.');

        // Requests editing the same file (e.g. an anchor update and a resolve) take turns.
        $store->lock('kite:comments:'.hash('sha1', $file), 10)
            ->block(5, function () use ($vault, $notePath, $file, $change): void {
                $comments = $change($this->read($vault, $notePath));

                if ($comments === []) {
                    File::delete($file);
                    $this->removeEmptyFolders($vault, dirname($file));

                    return;
                }

                File::ensureDirectoryExists(dirname($file));

                $temporary = dirname($file).DIRECTORY_SEPARATOR.'.'.basename($file).'.'.bin2hex(random_bytes(4)).'.tmp';

                File::put($temporary, json_encode(
                    ['format' => self::FORMAT, 'comments' => $comments],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                )."\n");
                File::move($temporary, $file);
            });
    }

    /**
     * The raw entries, unknown fields included, so writing never drops what another tool added.
     *
     * @return list<mixed>
     */
    private function read(Vault $vault, string $notePath): array
    {
        $file = $this->path($vault, $notePath);

        if (! File::exists($file)) {
            return [];
        }

        $decoded = json_decode(File::get($file), true);

        // Never overwrite a file we can't understand: someone's comments are in there.
        throw_unless(
            is_array($decoded) && is_array($decoded['comments'] ?? null) && array_is_list($decoded['comments']),
            RuntimeException::class,
            "The comments file [{$file}] could not be read.",
        );

        return $decoded['comments'];
    }

    /**
     * @return Comment|null
     */
    private function normalise(mixed $comment): ?array
    {
        if (! is_array($comment) || ! is_string($comment['id'] ?? null) || ! is_string($comment['quote'] ?? null)) {
            return null;
        }

        $string = fn (string $key): string => is_scalar($comment[$key] ?? null) ? (string) $comment[$key] : '';

        return [
            'id' => $comment['id'],
            'body' => $string('body'),
            'quote' => $comment['quote'],
            'prefix' => $string('prefix'),
            'suffix' => $string('suffix'),
            'resolved' => ($comment['resolved'] ?? false) === true,
            'created_at' => $string('created_at'),
            'updated_at' => $string('updated_at'),
        ];
    }

    /**
     * Tidy away comment folders left empty, up to `.kite` itself.
     */
    private function removeEmptyFolders(Vault $vault, string $folder): void
    {
        $stop = $vault->path.DIRECTORY_SEPARATOR.'.kite';

        while (str_starts_with($folder, $stop) && File::isDirectory($folder) && File::isEmptyDirectory($folder)) {
            File::deleteDirectory($folder);
            $folder = dirname($folder);
        }
    }
}
