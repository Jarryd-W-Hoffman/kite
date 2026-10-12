<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Models\Vault;
use App\Services\VaultFilesystem;
use Illuminate\Support\Facades\DB;

final readonly class IndexVault
{
    public function __construct(
        private VaultFilesystem $files,
        private IndexNote $indexNote,
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

        /** @var array<string, array{id: int, size: int, modified_at: int}> $indexed */
        $indexed = Note::query()
            ->where('vault_id', $vault->id)
            ->get(['id', 'path', 'size', 'modified_at'])
            ->mapWithKeys(fn (Note $note): array => [$note->path => [
                'id' => $note->id,
                'size' => $note->size,
                'modified_at' => $note->modified_at,
            ]])
            ->all();

        $summary = ['added' => 0, 'updated' => 0, 'removed' => 0];

        $removed = array_diff_key($indexed, $files);

        DB::transaction(function () use ($vault, $files, $indexed, $removed, &$summary): void {
            foreach (array_chunk(array_column($removed, 'id'), 500) as $ids) {
                $summary['removed'] += Note::query()->whereKey($ids)->toBase()->delete();
            }

            foreach ($files as $path => $stat) {
                $existing = $indexed[$path] ?? null;

                if ($existing !== null && $existing['size'] === $stat['size'] && $existing['modified_at'] === $stat['modified_at']) {
                    continue;
                }

                $this->indexNote->handle($vault, $path, resolveDanglingLinks: false);

                $summary[$existing === null ? 'added' : 'updated']++;
            }

            if ($summary['added'] > 0 || $summary['removed'] > 0) {
                $this->resolveLinks->handle($vault);
            }

            $vault->update(['indexed_at' => now()]);
        });

        return $summary;
    }
}
