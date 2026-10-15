<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Vault;
use App\Services\VaultFilesystem;
use InvalidArgumentException;

final readonly class CreateFolder
{
    public function __construct(private VaultFilesystem $files)
    {
        //
    }

    /**
     * Create a folder, and any missing parents, inside the vault and return its vault-relative path.
     */
    public function handle(Vault $vault, string $name, ?string $parent = null): string
    {
        $path = $this->files->normalise($parent === null || mb_trim($parent) === '' ? $name : $parent.'/'.$name);

        throw_if(
            $this->files->isIgnored($path),
            InvalidArgumentException::class,
            "The folder [{$path}] is ignored.",
        );

        throw_if(
            $this->files->folderExists($vault, $path) || $this->files->exists($vault, $path),
            InvalidArgumentException::class,
            "The folder [{$path}] already exists.",
        );

        $this->files->makeFolder($vault, $path);

        Vault::markTreeChanged($vault->id);

        return $path;
    }
}
