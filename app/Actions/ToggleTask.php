<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Services\VaultFilesystem;
use InvalidArgumentException;

final readonly class ToggleTask
{
    private const string TASK = '/^(\s*(?:[-*+]|\d+[.)])\s+\[)([ xX])(\]\s+)(\S.*)$/u';

    public function __construct(
        private VaultFilesystem $files,
        private SaveNote $saveNote,
    ) {
        //
    }

    /**
     * Tick or untick the task on a line of a note. The line must still hold the task as it was
     * listed, so a note edited in the meantime isn't changed in the wrong place.
     *
     * @throws InvalidArgumentException when the line no longer holds that task
     */
    public function handle(Note $note, int $line, string $text, bool $done): void
    {
        $contents = $this->files->read($note->vault, $note->path);
        $lines = explode("\n", $contents);
        $current = mb_rtrim($lines[$line - 1] ?? '', "\r");

        throw_unless(
            preg_match(self::TASK, $current, $match) === 1 && mb_trim($match[4]) === mb_trim($text),
            InvalidArgumentException::class,
            'That task has changed since the list was loaded.',
        );

        $lines[$line - 1] = $match[1].($done ? 'x' : ' ').$match[3].$match[4].(str_ends_with($lines[$line - 1], "\r") ? "\r" : '');

        $this->saveNote->handle($note, implode("\n", $lines));
    }
}
