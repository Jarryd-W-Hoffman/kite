<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Models\Vault;
use Illuminate\Support\Facades\DB;

final readonly class SearchNotes
{
    private const string MARK_START = "\u{E000}";

    private const string MARK_END = "\u{E001}";

    /**
     * Full-text search a vault, returning ranked results with an HTML-safe highlighted snippet.
     *
     * @return list<array{id: int, title: string, path: string, snippet: string}>
     */
    public function handle(Vault $vault, string $query, int $limit = 20): array
    {
        preg_match_all('/[\p{L}\p{N}_]+/u', $query, $matches);

        if ($matches[0] === []) {
            return array_values(Note::query()
                ->where('vault_id', $vault->id)
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
            WHERE notes_fts MATCH ? AND notes.vault_id = ?
            ORDER BY bm25(notes_fts, 10.0, 1.0)
            LIMIT ?',
            [self::MARK_START, self::MARK_END, '…', $match, $vault->id, $limit],
        );

        return array_map(fn (object $row): array => [
            'id' => (int) $row->id,
            'title' => (string) $row->title,
            'path' => (string) $row->path,
            'snippet' => str_replace(
                [self::MARK_START, self::MARK_END],
                ['<mark>', '</mark>'],
                e($this->plainText((string) $row->snippet)),
            ),
        ], $rows);
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
