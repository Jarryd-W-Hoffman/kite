<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\IndexVaultInBackground;
use App\Models\Vault;
use App\Services\IndexingProgress;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

final readonly class OpenVault
{
    public function __construct(
        private IndexVault $indexVault,
        private IndexAttachments $indexAttachments,
        private IndexingProgress $progress,
    ) {
        //
    }

    /**
     * Register (or re-open) a local folder as a vault and bring its index up to date.
     *
     * In the background the index is built by a queued job so large vaults open instantly.
     */
    public function handle(string $path, bool $inBackground = false): Vault
    {
        $realPath = realpath($path);

        throw_if(
            $realPath === false || ! File::isDirectory($realPath),
            InvalidArgumentException::class,
            "The folder [{$path}] does not exist.",
        );

        $realPath = mb_rtrim($realPath, DIRECTORY_SEPARATOR) ?: DIRECTORY_SEPARATOR;

        $vault = Vault::query()->firstOrCreate(['path' => $realPath], [
            'name' => basename($realPath) ?: $realPath,
        ]);

        $vault->update(['opened_at' => now()]);

        if ($inBackground) {
            $this->progress->start($vault);

            dispatch(new IndexVaultInBackground($vault));
        } else {
            $this->indexVault->handle($vault);
            $this->indexAttachments->handle($vault);
        }

        return $vault->refresh();
    }
}
