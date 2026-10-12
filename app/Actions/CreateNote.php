<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Models\Vault;
use App\Services\VaultFilesystem;

final readonly class CreateNote
{
    public function __construct(
        private VaultFilesystem $files,
        private IndexNote $indexNote,
    ) {
        //
    }

    /**
     * Create a new markdown file named after the title, picking a free file name when one is taken.
     */
    public function handle(Vault $vault, string $title, ?string $folder = null, ?string $contents = null): Note
    {
        $name = mb_trim((string) preg_replace('/[\\\\\/:*?"<>|#^\[\]\x00-\x1F]+/u', ' ', $title));
        $name = mb_trim((string) preg_replace('/\s+/u', ' ', $name), ' .') ?: 'Untitled';

        $prefix = $folder === null || mb_trim($folder) === '' ? '' : $this->files->normalise($folder).'/';

        $path = $prefix.$name.'.md';

        for ($suffix = 1; $this->files->exists($vault, $path); $suffix++) {
            $path = "{$prefix}{$name} {$suffix}.md";
        }

        $this->files->write($vault, $path, $contents ?? "# {$name}\n\n");

        return $this->indexNote->handle($vault, $path);
    }
}
