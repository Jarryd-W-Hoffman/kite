<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;

final readonly class ExtractToNote
{
    public function __construct(private CreateNote $createNote)
    {
        //
    }

    /**
     * Start a new note beside another one, holding a passage taken out of it. The editor replaces
     * the passage with a link to the new note.
     */
    public function handle(Note $from, string $title, string $text): Note
    {
        $folder = dirname($from->path);
        $title = mb_trim($title);

        return $this->createNote->handle(
            $from->vault,
            $title,
            $folder === '.' ? null : $folder,
            "# {$title}\n\n".mb_trim($text, "\n")."\n",
        );
    }
}
