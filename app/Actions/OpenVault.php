<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Vault;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

final readonly class OpenVault
{
    public function __construct(
        private IndexVault $indexVault,
        private IndexAttachments $indexAttachments,
    ) {
        //
    }

    /**
     * Register (or re-open) a local folder as a vault and bring its index up to date.
     */
    public function handle(string $path): Vault
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

        $this->indexVault->handle($vault);
        $this->indexAttachments->handle($vault);

        return $vault->refresh();
    }
}
