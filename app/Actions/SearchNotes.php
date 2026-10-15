<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Vault;
use App\Services\ColourGroupQuery;
use App\Services\Row;
use App\Services\Templates;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class SearchNotes
{
    private const string MARK_START = "\u{E000}";

    private const string MARK_END = "\u{E001}";

    /** A `field:value` filter term (optionally negated or quoted); `//` keeps URLs out. */
    private const string FILTER = '/(?<!\S)-?[\p{L}_][\p{L}\p{N}_-]*:(?!\/\/)(?:"[^"]*"|\S+)/u';

    public function __construct(private Templates $templates)
    {
        //
    }

    /**
     * Full-text search a vault, returning ranked results with an HTML-safe highlighted snippet.
     *
     * @return list<array{id: int, title: string, path: string, snippet: string}>
     */
    public function handle(Vault $vault, string $query, int $limit = 20): array
    {
        [$query, $filter] = $this->splitFilters($query);

        preg_match_all('/[\p{L}\p{N}_]+/u', $query, $matches);

        if ($matches[0] === []) {
            return array_values($this->filtered(DB::table('notes')->where('vault_id', $vault->id), $filter)
                ->latest('modified_at')
                ->limit($limit)
                ->get(['id', 'title', 'path'])
                ->map(fn (object $row): array => [
                    'id' => Row::int($row, 'id'),
                    'title' => Row::string($row, 'title'),
                    'path' => Row::string($row, 'path'),
                    'snippet' => '',
                ])
                ->all());
        }

        $match = implode(' ', array_map(fn (string $term): string => '"'.$term.'"*', $matches[0]));

        $rows = $this->filtered(DB::table('notes_fts')->join('notes', 'notes.id', '=', 'notes_fts.rowid'), $filter)
            ->selectRaw('notes.id, notes.title, notes.path, snippet(notes_fts, 1, ?, ?, ?, 16) AS snippet', [self::MARK_START, self::MARK_END, '…'])
            ->whereRaw('notes_fts MATCH ?', [$match])
            ->where('notes.vault_id', $vault->id)
            ->orderByRaw('bm25(notes_fts, 10.0, 1.0)')
            ->limit($limit)
            ->get();

        return array_values($rows->map(fn (object $row): array => [
            'id' => Row::int($row, 'id'),
            'title' => Row::string($row, 'title'),
            'path' => Row::string($row, 'path'),
            'snippet' => str_replace(
                [self::MARK_START, self::MARK_END],
                ['<mark>', '</mark>'],
                e($this->withoutTitle($this->plainText(Row::string($row, 'snippet')), Row::string($row, 'title'))),
            ),
        ])->all());
    }

    /**
     * Pull filter terms (`tag:work`, `folder:Projects`, `-tag:archived`, `status:active`) out of a
     * search, returning the rest of the text and the parsed filter (null when there is none).
     * Filters that don't parse are searched for as ordinary text.
     *
     * @return array{0: string, 1: ColourGroupQuery|null}
     */
    private function splitFilters(string $query): array
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

        return [mb_trim((string) preg_replace(self::FILTER, '', $query)), $filter];
    }

    /**
     * Narrow a search to the notes a filter allows, leaving templates out as filtered lists do.
     */
    private function filtered(Builder $notes, ?ColourGroupQuery $filter): Builder
    {
        if (! $filter instanceof ColourGroupQuery) {
            return $notes;
        }

        return $filter->applyTo($this->templates->exclude($notes, 'notes.path'));
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
