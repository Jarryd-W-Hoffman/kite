<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\VersionSource;
use App\Models\Note;
use App\Models\NoteLink;
use App\Services\VaultFilesystem;
use InvalidArgumentException;

final readonly class MergeNotes
{
    private const string FRONTMATTER = '/\A---[ \t]*\n(.*?)\n?(?:---|\.\.\.)[ \t]*(?:\n|\z)/s';

    public function __construct(
        private VaultFilesystem $files,
        private SaveNote $saveNote,
        private UpdateFrontmatter $updateFrontmatter,
        private RewriteLinks $rewriteLinks,
        private DeleteNote $deleteNote,
    ) {
        //
    }

    /**
     * Append one note to another and remove it: its text goes under a heading at the end of the
     * target, properties the target lacks are copied over, and links to it are pointed at the
     * target. The merged note goes to the trash, so it can still be recovered.
     */
    public function handle(Note $source, Note $target): Note
    {
        throw_if($source->is($target), InvalidArgumentException::class, 'A note cannot be merged into itself.');

        $vault = $target->vault;
        $contents = $this->files->read($vault, $target->path);
        $properties = $target->frontmatter + $source->frontmatter;

        if ($properties !== $target->frontmatter) {
            $contents = $this->updateFrontmatter->handle($contents, $properties);
        }

        $merged = $this->saveNote->handle(
            $target,
            mb_rtrim($contents)."\n\n".$this->section($source),
            VersionSource::Merge,
        );

        $backlinks = NoteLink::query()
            ->with('source')
            ->where('target_note_id', $source->id)
            ->get()
            ->groupBy('source_note_id');

        foreach ($backlinks as $links) {
            $from = $links->first()?->source;

            // The merged note's own links went into the target along with its text.
            $from = $from?->is($source) === true ? $merged : $from;

            if ($from instanceof Note) {
                $this->rewriteLinks->handle($from, $links->pluck('target_key')->unique()->all(), $merged->path);
            }
        }

        $this->deleteNote->handle($source);

        return $merged->refresh();
    }

    /**
     * The merged note's text as a section: its top heading (or its file name) as a second-level
     * heading, followed by the rest, without its frontmatter.
     */
    private function section(Note $note): string
    {
        $body = (string) preg_replace(self::FRONTMATTER, '', $this->files->read($note->vault, $note->path));
        $body = mb_ltrim($body, "\n");

        if (preg_match('/\A#[ \t]+(.+?)[ \t]*#*[ \t]*(?:\n|\z)/', $body, $heading) === 1) {
            $title = $heading[1];
            $body = mb_substr($body, mb_strlen($heading[0]));
        } else {
            $title = basename($note->path, '.md');
        }

        return mb_rtrim("## {$title}\n\n".$this->demoteHeadings(mb_trim($body, "\n")))."\n";
    }

    /**
     * Push each heading down a level so it sits beneath the new section heading, leaving fenced
     * code (where # starts comments) alone.
     */
    private function demoteHeadings(string $body): string
    {
        $fence = null;
        $lines = explode("\n", $body);

        foreach ($lines as $index => $line) {
            if (preg_match('/^[ \t]{0,3}(`{3,}|~{3,})/', $line, $marker) === 1) {
                if ($fence === null) {
                    $fence = $marker[1];
                } elseif (str_starts_with($marker[1], $fence)) {
                    $fence = null;
                }

                continue;
            }

            if ($fence === null && preg_match('/^#{1,5}[ \t]/', $line) === 1) {
                $lines[$index] = '#'.$line;
            }
        }

        return implode("\n", $lines);
    }
}
