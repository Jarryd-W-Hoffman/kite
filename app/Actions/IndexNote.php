<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Models\Vault;
use App\Services\LinkResolver;
use App\Services\Markdown\MarkdownParser;
use App\Services\Markdown\WikiLink;
use App\Services\VaultFilesystem;
use Illuminate\Support\Facades\DB;

final readonly class IndexNote
{
    public function __construct(
        private VaultFilesystem $files,
        private MarkdownParser $parser,
        private LinkResolver $resolver,
        private ResolveLinks $resolveLinks,
    ) {
        //
    }

    /**
     * Parse a markdown file from the vault and store its metadata, links, tags and search text.
     */
    public function handle(Vault $vault, string $path, bool $resolveDanglingLinks = true): Note
    {
        $path = $this->files->normalise($path);
        $contents = $this->files->read($vault, $path);
        $stat = $this->files->stat($vault, $path);
        $parsed = $this->parser->parse($contents, $path);
        $pathKey = WikiLink::keyFor($path);

        return DB::transaction(function () use ($vault, $path, $contents, $stat, $parsed, $pathKey, $resolveDanglingLinks): Note {
            $note = Note::query()->updateOrCreate([
                'vault_id' => $vault->id,
                'path' => $path,
            ], [
                'path_key' => $pathKey,
                'slug' => WikiLink::keyFor(basename($path)),
                'title' => $parsed->title,
                'frontmatter' => $parsed->frontmatter,
                'word_count' => $parsed->wordCount,
                'size' => $stat['size'],
                'modified_at' => $stat['modified_at'],
                'hash' => hash('xxh128', $contents),
            ]);

            DB::table('notes_fts')->where('rowid', $note->id)->delete();
            DB::table('notes_fts')->insert([
                'rowid' => $note->id,
                'title' => $parsed->title,
                'body' => $parsed->body,
            ]);

            $resolved = $this->resolver->resolve($vault, array_map(
                fn (WikiLink $link): string => $link->targetKey,
                $parsed->links,
            ));

            $note->outgoingLinks()->delete();
            $note->outgoingLinks()->insert(array_map(fn (WikiLink $link): array => [
                'vault_id' => $vault->id,
                'source_note_id' => $note->id,
                'target_note_id' => $resolved[$link->targetKey] ?? null,
                'target' => $link->target,
                'target_key' => $link->targetKey,
                'heading' => $link->heading,
                'alias' => $link->alias,
                'is_embed' => $link->isEmbed,
                'line' => $link->line,
            ], $parsed->links));

            $note->tags()->delete();
            $note->tags()->insert(array_map(fn (string $tag): array => [
                'vault_id' => $vault->id,
                'note_id' => $note->id,
                'name' => $tag,
            ], $parsed->tags));

            if ($resolveDanglingLinks && $note->wasRecentlyCreated) {
                $this->resolveLinks->handle($vault, $this->resolver->keysFor($pathKey));
            }

            return $note;
        });
    }
}
