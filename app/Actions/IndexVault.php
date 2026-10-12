<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Models\Vault;
use App\Services\LinkResolver;
use App\Services\Markdown\MarkdownParser;
use App\Services\NoteWriter;
use App\Services\Row;
use App\Services\VaultFilesystem;
use Illuminate\Support\Facades\DB;

final readonly class IndexVault
{
    public function __construct(
        private VaultFilesystem $files,
        private MarkdownParser $parser,
        private LinkResolver $resolver,
        private NoteWriter $writer,
        private ResolveLinks $resolveLinks,
    ) {
        //
    }

    /**
     * Incrementally sync the index with the vault folder, only re-parsing files that changed on disk.
     *
     * @return array{added: int, updated: int, removed: int}
     */
    public function handle(Vault $vault): array
    {
        $files = $this->files->markdownFiles($vault);
        $indexed = [];

        foreach (DB::table('notes')->where('vault_id', $vault->id)->get(['id', 'path', 'size', 'modified_at']) as $row) {
            $indexed[Row::string($row, 'path')] = [
                'id' => Row::int($row, 'id'),
                'size' => Row::int($row, 'size'),
                'modified_at' => Row::int($row, 'modified_at'),
            ];
        }

        $removed = array_diff_key($indexed, $files);
        $changed = array_filter($files, fn (array $stat, string $path): bool => ! isset($indexed[$path])
            || $indexed[$path]['size'] !== $stat['size']
            || $indexed[$path]['modified_at'] !== $stat['modified_at'], ARRAY_FILTER_USE_BOTH);

        $summary = ['added' => 0, 'updated' => 0, 'removed' => 0];

        if ($removed === [] && $changed === []) {
            return $summary;
        }

        DB::transaction(function () use ($vault, $indexed, $removed, $changed, &$summary): void {
            foreach (array_chunk(array_column($removed, 'id'), 500) as $ids) {
                $summary['removed'] += Note::query()->whereKey($ids)->toBase()->delete();
            }

            $pending = [];
            $addedKeys = [];

            foreach ($changed as $path => $stat) {
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
            }

            $index = $this->resolver->index($vault);
            $rows = [];

            foreach ($pending as [$noteId, $links]) {
                array_push($rows, ...$this->writer->linkRows($vault, $noteId, $links, $index));
            }

            $this->writer->insertLinks($rows);

            if ($summary['added'] > 0 || $summary['removed'] > 0) {
                $this->resolveLinks->handle($vault, $addedKeys);
            }

            $vault->update(['indexed_at' => now()]);
        });

        return $summary;
    }
}
