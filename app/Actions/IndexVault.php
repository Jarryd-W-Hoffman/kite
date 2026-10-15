<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Models\Vault;
use App\Services\IndexingProgress;
use App\Services\LinkResolver;
use App\Services\Markdown\MarkdownParser;
use App\Services\Markdown\WikiLink;
use App\Services\NoteWriter;
use App\Services\Row;
use App\Services\VaultFilesystem;
use Closure;
use Illuminate\Support\Facades\DB;

final readonly class IndexVault
{
    private const int PROGRESS_EVERY = 50;

    public function __construct(
        private VaultFilesystem $files,
        private MarkdownParser $parser,
        private LinkResolver $resolver,
        private NoteWriter $writer,
        private ResolveLinks $resolveLinks,
        private IndexingProgress $progress,
    ) {
        //
    }

    /**
     * Incrementally sync the index with the vault folder, only re-parsing files that changed on disk.
     *
     * Only one index run per vault happens at a time; a concurrent call returns without changes.
     * Work is committed in batches so other requests are never blocked for long.
     *
     * @param  (Closure(int, int): void)|null  $onProgress  Called with (notes done, notes to index).
     * @return array{added: int, updated: int, removed: int}
     */
    public function handle(Vault $vault, ?Closure $onProgress = null): array
    {
        $summary = ['added' => 0, 'updated' => 0, 'removed' => 0];

        $lock = $this->progress->lock($vault);

        if (! $lock->get()) {
            return $summary;
        }

        try {
            $files = $this->files->markdownFiles($vault);
            $indexed = $this->indexedNotes($vault);

            $removed = array_diff_key($indexed, $files);
            $changed = array_filter($files, fn (array $stat, string $path): bool => ! isset($indexed[$path])
                || $indexed[$path]['size'] !== $stat['size']
                || $indexed[$path]['modified_at'] !== $stat['modified_at'], ARRAY_FILTER_USE_BOTH);

            if ($removed !== [] || $changed !== []) {
                $this->index($vault, $indexed, $removed, $changed, $summary, $onProgress);
            }
        } finally {
            $lock->release();
        }

        return $summary;
    }

    /**
     * @return array<string, array{id: int, size: int, modified_at: int}>
     */
    private function indexedNotes(Vault $vault): array
    {
        $indexed = [];

        foreach (DB::table('notes')->where('vault_id', $vault->id)->get(['id', 'path', 'size', 'modified_at']) as $row) {
            $indexed[Row::string($row, 'path')] = [
                'id' => Row::int($row, 'id'),
                'size' => Row::int($row, 'size'),
                'modified_at' => Row::int($row, 'modified_at'),
            ];
        }

        return $indexed;
    }

    /**
     * @param  array<string, array{id: int, size: int, modified_at: int}>  $indexed
     * @param  array<string, array{id: int, size: int, modified_at: int}>  $removed
     * @param  array<string, array{size: int, modified_at: int}>  $changed
     * @param  array{added: int, updated: int, removed: int}  $summary
     * @param  (Closure(int, int): void)|null  $onProgress
     */
    private function index(Vault $vault, array $indexed, array $removed, array $changed, array &$summary, ?Closure $onProgress): void
    {
        $total = count($changed);
        $report = function (int $done) use ($onProgress, $total): void {
            if ($onProgress instanceof Closure) {
                $onProgress($done, $total);
            }
        };

        $report(0);

        foreach (array_chunk(array_column($removed, 'id'), 500) as $ids) {
            $summary['removed'] += Note::query()->whereKey($ids)->toBase()->delete();
        }

        if ($summary['removed'] > 0) {
            Vault::markTreeChanged($vault->id);
        }

        /** @var list<array{0: int, 1: list<WikiLink>}> $pending */
        $pending = [];
        $addedKeys = [];

        foreach (array_chunk($changed, $this->batchSize(), preserve_keys: true) as $batch) {
            DB::transaction(function () use ($vault, $indexed, $batch, &$summary, &$pending, &$addedKeys, $report): void {
                foreach ($batch as $path => $stat) {
                    $path = (string) $path;
                    $contents = $this->files->read($vault, $path);
                    $parsed = $this->parser->parse($contents, $path);
                    $note = $this->writer->write($vault, $path, $contents, $stat, $parsed);

                    $pending[] = [$note->id, $parsed->links];

                    if (isset($indexed[$path])) {
                        $summary['updated']++;
                    } else {
                        $summary['added']++;
                        array_push($addedKeys, ...$this->resolver->keysFor($note->path_key));
                    }

                    $done = $summary['added'] + $summary['updated'];

                    if ($done % self::PROGRESS_EVERY === 0) {
                        $report($done);
                    }
                }
            });
        }

        $index = $this->resolver->index($vault);

        foreach (array_chunk($pending, $this->batchSize()) as $batch) {
            $rows = [];

            foreach ($batch as [$noteId, $links]) {
                array_push($rows, ...$this->writer->linkRows($vault, $noteId, $links, $index));
            }

            DB::transaction(fn () => $this->writer->insertLinks($rows));
        }

        if ($summary['added'] > 0 || $summary['removed'] > 0) {
            $this->resolveLinks->handle($vault, $addedKeys);
        }

        $vault->update(['indexed_at' => now()]);

        $report($total);
    }

    /**
     * @return int<1, max>
     */
    private function batchSize(): int
    {
        $size = config('kite.indexing.batch_size');

        return is_int($size) && $size > 0 ? $size : 200;
    }
}
