<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Models\Vault;
use App\Services\ColourGroupQuery;
use App\Services\ColourGroups;
use App\Services\Templates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class SearchNotes
{
    private const string MARK_START = "\u{E000}";

    private const string MARK_END = "\u{E001}";

    /** A `field:value` filter term (optionally negated or quoted); `//` keeps URLs out. */
    private const string FILTER = '/(?<!\S)-?[\p{L}_][\p{L}\p{N}_-]*:(?!\/\/)(?:"[^"]*"|\S+)/u';

    public function __construct(
        private ColourGroups $notes,
        private Templates $templates,
    ) {
        //
    }

    /**
     * Full-text search a vault, returning ranked results with an HTML-safe highlighted snippet.
     *
     * @return list<array{id: int, title: string, path: string, snippet: string}>
     */
    public function handle(Vault $vault, string $query, int $limit = 20): array
    {
        [$query, $allowed] = $this->applyFilters($vault, $query);

        if ($allowed === []) {
            return [];
        }

        preg_match_all('/[\p{L}\p{N}_]+/u', $query, $matches);

        if ($matches[0] === []) {
            return array_values(Note::query()
                ->where('vault_id', $vault->id)
                ->when($allowed !== null, fn (Builder $notes): Builder => $notes->whereIn('id', $allowed ?? []))
                ->latest('modified_at')
                ->limit($limit)
                ->get(['id', 'title', 'path'])
                ->map(fn (Note $note): array => [
                    'id' => $note->id,
                    'title' => $note->title,
                    'path' => $note->path,
                    'snippet' => '',
                ])
                ->all());
        }

        $match = implode(' ', array_map(fn (string $term): string => '"'.$term.'"*', $matches[0]));

        /** @var list<object{id: int, title: string, path: string, snippet: string}> $rows */
        $rows = DB::select(
            'SELECT notes.id, notes.title, notes.path, snippet(notes_fts, 1, ?, ?, ?, 16) AS snippet
            FROM notes_fts
            JOIN notes ON notes.id = notes_fts.rowid
            WHERE notes_fts MATCH ? AND notes.vault_id = ?'.($allowed === null ? '' : ' AND notes.id IN ('.implode(',', array_fill(0, count($allowed), '?')).')').'
            ORDER BY bm25(notes_fts, 10.0, 1.0)
            LIMIT ?',
            [self::MARK_START, self::MARK_END, '…', $match, $vault->id, ...($allowed ?? []), $limit],
        );

        return array_map(fn (object $row): array => [
            'id' => (int) $row->id,
            'title' => (string) $row->title,
            'path' => (string) $row->path,
            'snippet' => str_replace(
                [self::MARK_START, self::MARK_END],
                ['<mark>', '</mark>'],
                e($this->withoutTitle($this->plainText((string) $row->snippet), (string) $row->title)),
            ),
        ], $rows);
    }

    /**
     * Pull filter terms (`tag:work`, `folder:Projects`, `-tag:archived`, `status:active`) out of a
     * search, returning the rest of the text and the ids of the notes the filters allow (null when
     * there are no filters). Filters that don't parse are searched for as ordinary text.
     *
     * @return array{0: string, 1: list<int>|null}
     */
    private function applyFilters(Vault $vault, string $query): array
    {
        // A filter still being typed ("tag:") shouldn't empty the results while it's unfinished.
        $query = (string) preg_replace('/(?<!\S)-?[\p{L}_][\p{L}\p{N}_-]*:(?=\s|$)/u', '', $query);

        preg_match_all(self::FILTER, $query, $terms);

        if ($terms[0] === []) {
            return [$query, null];
        }

        try {
            $filter = ColourGroupQuery::parse(implode(' ', $terms[0]));
        } catch (InvalidArgumentException) {
            return [$query, null];
        }

        $allowed = [];

        foreach ($this->notes->notes($vault) as $id => $note) {
            if (! $this->templates->contains($note['path']) && $filter->matches($note)) {
                $allowed[] = $id;
            }
        }

        return [mb_trim((string) preg_replace(self::FILTER, '', $query)), $allowed];
    }

    /**
     * A snippet from the top of a note starts with its heading, which repeats the title shown
     * right above it; drop it, highlight markers and all.
     */
    private function withoutTitle(string $snippet, string $title): string
    {
        $marks = '['.preg_quote(self::MARK_START.self::MARK_END, '/').']*';
        $pattern = '/^'.$marks.implode($marks, array_map(
            fn (string $character): string => preg_quote($character, '/'),
            mb_str_split($title),
        )).$marks.'(?:\s+|$)/u';

        $stripped = (string) preg_replace($pattern, '', $snippet, 1);

        return $stripped === '' ? $snippet : $stripped;
    }

    /**
     * Strip the most common markdown syntax so snippets read as prose.
     */
    private function plainText(string $snippet): string
    {
        $snippet = (string) preg_replace('/(^|\s)(#{1,6}|>|[-*+]|\d+\.)\s+/u', '$1', $snippet);
        $snippet = str_replace(['[[', ']]', '**', '__', '`'], '', $snippet);

        return mb_trim((string) preg_replace('/\s+/u', ' ', $snippet));
    }
}
