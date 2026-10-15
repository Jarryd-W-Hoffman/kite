<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Services\NoteLocks;
use InvalidArgumentException;
use SensitiveParameter;

final readonly class ExtractToNote
{
    public function __construct(
        private CreateNote $createNote,
        private NoteLocks $locks,
    ) {
        //
    }

    /**
     * Start a new note beside another one, holding a passage taken out of it. The editor replaces
     * the passage with a link to the new note. A passage from a locked note goes into a locked
     * note, so it needs the vault's key.
     */
    public function handle(Note $from, string $title, string $text, #[SensitiveParameter] ?string $key = null): Note
    {
        throw_if($from->locked && $key === null, InvalidArgumentException::class, 'Unlock the vault to extract from a locked note.');

        $folder = dirname($from->path);
        $title = mb_trim($title);
        $contents = "# {$title}\n\n".mb_trim($text, "\n")."\n";

        return $this->createNote->handle(
            $from->vault,
            $title,
            $folder === '.' ? null : $folder,
            $from->locked ? $this->locks->lock($contents, $key) : $contents,
        );
    }
}
