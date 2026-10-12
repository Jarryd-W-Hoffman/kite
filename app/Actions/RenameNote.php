<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Models\NoteLink;
use App\Services\LinkResolver;
use App\Services\Markdown\WikiLink;
use App\Services\VaultFilesystem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class RenameNote
{
    private const string LINK_PATTERN = '/(!?)\[\[([^\[\]\n]+?)\]\]/u';

    public function __construct(
        private VaultFilesystem $files,
        private IndexNote $indexNote,
        private ResolveLinks $resolveLinks,
        private LinkResolver $resolver,
    ) {
        //
    }

    /**
     * Move a note to a new path and rewrite every wiki link that pointed at it.
     */
    public function handle(Note $note, string $path): Note
    {
        $vault = $note->vault;
        $path = $this->files->normalise($path);
        $path = preg_match('/\.md$/i', $path) === 1 ? $path : $path.'.md';

        if ($path === $note->path) {
            return $note;
        }

        throw_if(
            $this->files->exists($vault, $path) && mb_strtolower($path) !== mb_strtolower($note->path),
            InvalidArgumentException::class,
            "A note already exists at [{$path}].",
        );

        $backlinks = NoteLink::query()
            ->with('source')
            ->where('target_note_id', $note->id)
            ->where('source_note_id', '!=', $note->id)
            ->get();

        $this->files->move($vault, $note->path, $path);

        return DB::transaction(function () use ($note, $vault, $path, $backlinks): Note {
            $selfLinkKeys = $note->outgoingLinks()->where('target_note_id', $note->id)->pluck('target_key')->all();

            $note->update(['path' => $path]);

            $renamed = $this->indexNote->handle($vault, $path, resolveDanglingLinks: false);

            foreach ($backlinks->groupBy('source_note_id') as $links) {
                $source = $links->first()?->source;

                if ($source instanceof Note) {
                    $this->rewriteLinks($source, $links->pluck('target_key')->unique()->all(), $path);
                }
            }

            if ($selfLinkKeys !== []) {
                $this->rewriteLinks($renamed, $selfLinkKeys, $path);
            }

            $this->resolveLinks->handle($vault, $this->resolver->keysFor($renamed->path_key));

            return $renamed->refresh();
        });
    }

    /**
     * @param  array<mixed>  $keys
     */
    private function rewriteLinks(Note $source, array $keys, string $path): void
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
