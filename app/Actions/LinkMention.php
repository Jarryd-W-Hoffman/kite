<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Services\Markdown\MentionMatcher;
use App\Services\VaultFilesystem;

final readonly class LinkMention
{
    public function __construct(
        private VaultFilesystem $files,
        private MentionMatcher $matcher,
        private FindUnlinkedMentions $mentions,
        private SaveNote $saveNote,
    ) {
        //
    }

    /**
     * Turn the plain-text mention of the target on the given line of the source note into a wiki link.
     */
    public function handle(Note $source, Note $target, int $line): bool
    {
        if ($source->locked) {
            return false;
        }

        $contents = $this->files->read($source->vault, $source->path);

        $linked = $this->matcher->link(
            $contents,
            $line,
            $this->mentions->termsFor($target),
            (string) preg_replace('/\.md$/i', '', basename($target->path)),
        );

        if ($linked === null) {
            return false;
        }

        $this->saveNote->handle($source, $linked);

        return true;
    }
}
