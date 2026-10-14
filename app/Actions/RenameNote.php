<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Models\NoteLink;
use App\Services\CommentStore;
use App\Services\LinkResolver;
use App\Services\VaultFilesystem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class RenameNote
{
    public function __construct(
        private VaultFilesystem $files,
        private IndexNote $indexNote,
        private ResolveLinks $resolveLinks,
        private LinkResolver $resolver,
        private CommentStore $comments,
        private RewriteLinks $rewriteLinks,
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
        $this->comments->move($vault, $note->path, $path);

        return DB::transaction(function () use ($note, $vault, $path, $backlinks): Note {
            $selfLinkKeys = $note->outgoingLinks()->where('target_note_id', $note->id)->pluck('target_key')->all();

            $note->update(['path' => $path]);

            $renamed = $this->indexNote->handle($vault, $path, resolveDanglingLinks: false);

            foreach ($backlinks->groupBy('source_note_id') as $links) {
                $source = $links->first()?->source;

                if ($source instanceof Note) {
                    $this->rewriteLinks->handle($source, $links->pluck('target_key')->unique()->all(), $path);
                }
            }

            if ($selfLinkKeys !== []) {
                $this->rewriteLinks->handle($renamed, $selfLinkKeys, $path);
            }

            $this->resolveLinks->handle($vault, $this->resolver->keysFor($renamed->path_key));

            return $renamed->refresh();
        });
    }
}
