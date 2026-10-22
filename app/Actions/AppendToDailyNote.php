<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Models\Vault;
use App\Services\LocalTime;
use App\Services\VaultFilesystem;
use InvalidArgumentException;

final readonly class AppendToDailyNote
{
    public function __construct(
        private OpenDailyNote $openDailyNote,
        private VaultFilesystem $files,
        private SaveNote $saveNote,
        private LocalTime $time,
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

        throw_if($note->locked, InvalidArgumentException::class, "Today's note is locked.");

        $lines = preg_split('/\R/', mb_trim($text)) ?: [];
        $entry = '- '.$this->time->now()->format('H:i').' '.array_shift($lines);

        foreach ($lines as $line) {
            $entry .= "\n  ".$line;
        }

        $separator = $contents === '' || str_ends_with($contents, "\n") ? '' : "\n";

        return $this->saveNote->handle($note, $contents.$separator.$entry."\n");
    }
}
