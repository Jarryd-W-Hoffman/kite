<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Models\Vault;
use App\Services\LinkResolver;
use App\Services\Markdown\MarkdownParser;
use App\Services\Markdown\WikiLink;
use App\Services\NoteWriter;
use App\Services\VaultFilesystem;
use Illuminate\Support\Facades\DB;

final readonly class IndexNote
{
    public function __construct(
        private VaultFilesystem $files,
        private MarkdownParser $parser,
        private LinkResolver $resolver,
        private NoteWriter $writer,
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

        $store = function () use ($vault, $path, $contents, $stat, $parsed, $resolveDanglingLinks): Note {
            $note = $this->writer->write($vault, $path, $contents, $stat, $parsed);

            $resolved = $this->resolver->resolve($vault, array_map(
                fn (WikiLink $link): string => $link->targetKey,
                $parsed->links,
            ));

            $this->writer->insertLinks($this->writer->linkRows($vault, $note->id, $parsed->links, $resolved));

            if ($resolveDanglingLinks && $note->wasRecentlyCreated) {
                $this->resolveLinks->handle($vault, $this->resolver->keysFor($note->path_key));
            }

            return $note;
        };

        // Laravel never releases nested savepoints until the outer commit, so avoid nesting transactions.
        return DB::transactionLevel() > 0 ? $store() : DB::transaction($store);
    }
}
