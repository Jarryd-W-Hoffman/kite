<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Vault;
use App\Services\AttachmentTypes;
use App\Services\Row;
use App\Services\VaultFilesystem;
use Illuminate\Support\Facades\DB;

final readonly class IndexAttachments
{
    public function __construct(
        private VaultFilesystem $files,
        private AttachmentTypes $types,
    ) {
        //
    }

    /**
     * Sync the attachment index with the vault folder, returning how many attachments changed.
     */
    public function handle(Vault $vault): int
    {
        $files = $this->files->filesWithExtensions($vault, $this->types->extensions());
        $indexed = [];

        foreach (DB::table('attachments')->where('vault_id', $vault->id)->get(['id', 'path', 'size', 'modified_at']) as $row) {
            $indexed[Row::string($row, 'path')] = [
                'id' => Row::int($row, 'id'),
                'size' => Row::int($row, 'size'),
                'modified_at' => Row::int($row, 'modified_at'),
            ];
        }

        $removed = array_column(array_diff_key($indexed, $files), 'id');
        $rows = [];

        foreach ($files as $path => $stat) {
            $existing = $indexed[$path] ?? null;

            if ($existing !== null && $existing['size'] === $stat['size'] && $existing['modified_at'] === $stat['modified_at']) {
                continue;
            }

            $rows[] = [
                'vault_id' => $vault->id,
                'path' => $path,
                'path_key' => mb_strtolower($path),
                'name_key' => mb_strtolower(basename($path)),
                'extension' => $this->types->extension($path),
                'size' => $stat['size'],
                'modified_at' => $stat['modified_at'],
            ];
        }

        $added = array_filter($rows, fn (array $row): bool => ! isset($indexed[$row['path']]));

        DB::transaction(function () use ($removed, $rows): void {
            foreach (array_chunk($removed, 500) as $ids) {
                DB::table('attachments')->whereIn('id', $ids)->delete();
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('attachments')->upsert($chunk, ['vault_id', 'path'], ['path_key', 'name_key', 'extension', 'size', 'modified_at']);
            }
        });

        // The file tree lists attachments, so one appearing or going is a change to it.
        if ($removed !== [] || $added !== []) {
            Vault::markTreeChanged($vault->id);
        }

        return count($removed) + count($rows);
    }
}
