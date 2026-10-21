<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Models\Vault;
use App\Services\CommentStore;
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
        private CommentStore $comments,
    ) {
        //
    }

    /**
     * Incrementally sync the index with the vault folder, only re-parsing files that changed on disk.
     *
     * Only one index run per vault happens at a time; a concurrent call returns without changes.
     * Work is committed in batches so other requests are never blocked for long. A note moved or
     * renamed outside Kite keeps its row, and so its history, star and comments.
     *
     * @param  (Closure(int, int): void)|null  $onProgress  Called with (notes done, notes to index).
     * @return array{added: int, updated: int, removed: int, moved: int}
     */
    public function handle(Vault $vault, ?Closure $onProgress = null): array
    {
        $summary = ['added' => 0, 'updated' => 0, 'removed' => 0, 'moved' => 0];

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

            $moves = $this->moves($vault, $removed, array_diff_key($changed, $indexed));
            $removed = array_diff_key($removed, $moves);

            if ($removed !== [] || $changed !== []) {
                $this->index($vault, $indexed, $removed, $changed, $moves, $summary, $onProgress);
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
     * @param  array<string, string>  $moves  Old path => new path.
     * @param  array{added: int, updated: int, removed: int, moved: int}  $summary
     * @param  (Closure(int, int): void)|null  $onProgress
     */
    private function index(Vault $vault, array $indexed, array $removed, array $changed, array $moves, array &$summary, ?Closure $onProgress): void
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

        /** @var list<string> $addedKeys */
        $addedKeys = [];

        // A moved note's row follows its file, so re-indexing it below updates it in place. Links
        // to its old name and to its new one are resolved again.
        foreach ($moves as $from => $to) {
            $from = (string) $from;

            DB::table('notes')->where('vault_id', $vault->id)->where('path', $from)->update(['path' => $to, 'path_key' => WikiLink::keyFor($to), 'slug' => WikiLink::keyFor(basename($to))]);
            $this->comments->move($vault, $from, $to);

            array_push($addedKeys, ...$this->resolver->keysFor(WikiLink::keyFor($from)), ...$this->resolver->keysFor(WikiLink::keyFor($to)));
            $summary['moved']++;
        }

        if ($summary['removed'] > 0 || $summary['moved'] > 0) {
            Vault::markTreeChanged($vault->id);
        }

        $movedTo = array_flip($moves);

        /** @var list<array{0: int, 1: list<WikiLink>}> $pending */
        $pending = [];
        $done = 0;

        foreach (array_chunk($changed, $this->batchSize(), preserve_keys: true) as $batch) {
            DB::transaction(function () use ($vault, $indexed, $movedTo, $batch, &$summary, &$pending, &$addedKeys, &$done, $report): void {
                // Readers only see the batch once it commits, so one new tree revision covers it.
                $treeChanged = false;
                $onTreeChange = function () use (&$treeChanged): void {
                    $treeChanged = true;
                };

                foreach ($batch as $path => $stat) {
                    $path = (string) $path;
                    $contents = $this->files->read($vault, $path);
                    $parsed = $this->parser->parse($contents, $path);
                    $isNew = ! isset($indexed[$path]) && ! isset($movedTo[$path]);
                    $note = $this->writer->write($vault, $path, $contents, $stat, $parsed, $isNew, $onTreeChange);

                    $pending[] = [$note->id, $parsed->links];

                    if (isset($indexed[$path])) {
                        $summary['updated']++;
                    } elseif (! isset($movedTo[$path])) {
                        $summary['added']++;
                        array_push($addedKeys, ...$this->resolver->keysFor($note->path_key));
                    }

                    $done++;

                    if ($done % self::PROGRESS_EVERY === 0) {
                        $report($done);
                    }
                }

                if ($treeChanged) {
                    Vault::markTreeChanged($vault->id);
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

        if ($summary['added'] > 0 || $summary['removed'] > 0 || $summary['moved'] > 0) {
            $this->resolveLinks->handle($vault, $addedKeys);
        }

        $vault->update(['indexed_at' => now()]);

        $report($total);
    }

    /**
     * Notes moved or renamed outside Kite: a removed note whose exact text turns up at a new path.
     * Only new files the size of a removed note are read. When several removed notes have the same
     * text, one with the same file name is preferred.
     *
     * @param  array<string, array{id: int, size: int, modified_at: int}>  $removed
     * @param  array<string, array{size: int, modified_at: int}>  $added
     * @return array<string, string> Old path => new path.
     */
    private function moves(Vault $vault, array $removed, array $added): array
    {
        $sizes = array_flip(array_column($removed, 'size'));
        $added = array_filter($added, fn (array $stat): bool => isset($sizes[$stat['size']]));

        if ($added === []) {
            return [];
        }

        /** @var array<string, list<string>> $byHash */
        $byHash = [];

        foreach (array_chunk(array_column($removed, 'id'), 500) as $ids) {
            foreach (DB::table('notes')->whereIn('id', $ids)->get(['path', 'hash']) as $row) {
                $byHash[Row::string($row, 'hash')][] = Row::string($row, 'path');
            }
        }

        $moves = [];

        foreach (array_keys($added) as $path) {
            $path = (string) $path;
            $hash = hash('xxh128', $this->files->read($vault, $path));
            $candidates = $byHash[$hash] ?? [];

            if ($candidates === []) {
                continue;
            }

            $from = array_find($candidates, fn (string $candidate): bool => basename($candidate) === basename($path)) ?? $candidates[0];

            $moves[$from] = $path;
            $byHash[$hash] = array_values(array_diff($candidates, [$from]));
        }

        return $moves;
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
