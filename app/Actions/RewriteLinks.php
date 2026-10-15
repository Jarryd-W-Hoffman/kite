<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Services\Markdown\WikiLink;
use App\Services\VaultFilesystem;

final readonly class RewriteLinks
{
    private const string LINK_PATTERN = '/(!?)\[\[([^\[\]\n]+?)\]\]/u';

    public function __construct(
        private VaultFilesystem $files,
        private IndexNote $indexNote,
    ) {
        //
    }

    /**
     * Point the wiki links in a note whose targets match any of the keys at another path, keeping
     * their headings and aliases. Links written as paths stay paths; bare names stay names.
     *
     * @param  array<mixed>  $keys
     */
    public function handle(Note $source, array $keys, string $path): void
    {
        $vault = $source->vault;
        $contents = $this->files->read($vault, $source->path);
        $pathWithoutExtension = (string) preg_replace('/\.md$/i', '', $path);
        $name = basename($pathWithoutExtension);

        $rewritten = (string) preg_replace_callback(self::LINK_PATTERN, function (array $match) use ($keys, $pathWithoutExtension, $name): string {
            $inner = $match[2];
            $targetEnd = strcspn($inner, '#|');
            $target = mb_substr($inner, 0, $targetEnd);

            if (! in_array(WikiLink::keyFor($target), $keys, true)) {
                return $match[0];
            }

            $replacement = str_contains(WikiLink::keyFor($target), '/') ? $pathWithoutExtension : $name;

            return $match[1].'[['.$replacement.mb_substr($inner, $targetEnd).']]';
        }, $contents);

        if ($rewritten !== $contents) {
            $this->files->write($vault, $source->path, $rewritten);
            $this->indexNote->handle($vault, $source->path, resolveDanglingLinks: false);
        }
    }
}
