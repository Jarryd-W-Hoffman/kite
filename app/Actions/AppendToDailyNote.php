<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Models\Vault;
use App\Services\VaultFilesystem;

final readonly class AppendToDailyNote
{
    public function __construct(
        private OpenDailyNote $openDailyNote,
        private VaultFilesystem $files,
        private SaveNote $saveNote,
    ) {
        //
    }

    /**
     * Append a timestamped bullet to today's note, indenting continuation lines under it.
     */
    public function handle(Vault $vault, string $text): Note
    {
        $note = $this->openDailyNote->handle($vault);
        $contents = $this->files->read($vault, $note->path);

        $lines = preg_split('/\R/', mb_trim($text)) ?: [];
        $entry = '- '.now()->format('H:i').' '.array_shift($lines);

        foreach ($lines as $line) {
            $entry .= "\n  ".$line;
        }

        $separator = $contents === '' || str_ends_with($contents, "\n") ? '' : "\n";

        return $this->saveNote->handle($note, $contents.$separator.$entry."\n");
    }
}
