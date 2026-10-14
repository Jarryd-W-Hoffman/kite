<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Services\Markdown\MentionMatcher;
use App\Services\Row;
use App\Services\Templates;
use App\Services\VaultFilesystem;
use Illuminate\Support\Facades\DB;

final readonly class FindUnlinkedMentions
{
    private const int MIN_TERM_LENGTH = 3;

    private const int CANDIDATE_LIMIT = 200;

    public function __construct(
        private VaultFilesystem $files,
        private MentionMatcher $matcher,
        private Templates $templates,
    ) {
        //
    }

    /**
     * The names a note can be mentioned by: its title, file name and frontmatter aliases.
     *
     * @return list<string>
     */
    public function termsFor(Note $note): array
    {
        $aliases = $note->frontmatter['aliases'] ?? $note->frontmatter['alias'] ?? [];
        $aliases = is_string($aliases) ? [$aliases] : (is_array($aliases) ? array_filter($aliases, is_string(...)) : []);

        $terms = [];

        foreach ([$note->title, (string) preg_replace('/\.md$/i', '', basename($note->path)), ...$aliases] as $term) {
            $term = mb_trim($term);

            if (mb_strlen($term) >= self::MIN_TERM_LENGTH) {
                $terms[mb_strtolower($term)] = $term;
            }
        }

        return array_values($terms);
    }

    /**
     * Notes that mention this note in plain text without linking to it.
     *
     * @return list<array{id: int, title: string, path: string, line: int, term: string, context: string}>
     */
    public function handle(Note $note, int $limit = 50): array
    {
        $terms = $this->termsFor($note);

        if ($terms === []) {
            return [];
        }

        $phrases = implode(' OR ', array_map(
            fn (string $term): string => '"'.str_replace('"', '""', $term).'"',
            $terms,
        ));

        $linkingNoteIds = DB::table('note_links')->where('target_note_id', $note->id)->pluck('source_note_id')->all();

        $candidates = $this->templates->exclude(DB::table('notes_fts')
            ->join('notes', 'notes.id', '=', 'notes_fts.rowid'), 'notes.path')
            ->where('notes.vault_id', $note->vault_id)
            ->where('notes.id', '!=', $note->id)
            ->whereNotIn('notes.id', $linkingNoteIds)
            ->whereRaw('notes_fts MATCH ?', [$phrases])
            ->orderByRaw('bm25(notes_fts)')
            ->limit(self::CANDIDATE_LIMIT)
            ->get(['notes.id', 'notes.title', 'notes.path']);

        $mentions = [];

        foreach ($candidates as $candidate) {
            $path = Row::string($candidate, 'path');

            if (! $this->files->exists($note->vault, $path)) {
                continue;
            }

            $match = $this->matcher->find($this->files->read($note->vault, $path), $terms);

            if ($match !== null) {
                $mentions[] = [
                    'id' => Row::int($candidate, 'id'),
                    'title' => Row::string($candidate, 'title'),
                    'path' => $path,
                    ...$match,
                ];
            }

            if (count($mentions) >= $limit) {
                break;
            }
        }

        return $mentions;
    }
}
