<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Note;
use App\Models\Vault;
use App\Services\Markdown\ParsedNote;
use App\Services\Markdown\WikiLink;
use Illuminate\Support\Facades\DB;

/**
 * Low-level index writes shared by single-note and bulk indexing, using the query builder for speed.
 */
final readonly class NoteWriter
{
    private const int LINK_INSERT_CHUNK = 500;

    /**
     * Upsert a note's row, search text and tags, clearing any links it previously had.
     *
     * @param  array{size: int, modified_at: int}  $stat
     */
    public function write(Vault $vault, string $path, string $contents, array $stat, ParsedNote $parsed): Note
    {
        $now = now()->toDateTimeString();
        $attributes = [
            'path_key' => WikiLink::keyFor($path),
            'slug' => WikiLink::keyFor(basename($path)),
            'title' => $parsed->title,
            'frontmatter' => json_encode($parsed->frontmatter, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'word_count' => $parsed->wordCount,
            'locked' => $parsed->locked,
            'size' => $stat['size'],
            'modified_at' => $stat['modified_at'],
            'hash' => hash('xxh128', $contents),
            'updated_at' => $now,
        ];

        $existing = DB::table('notes')->where('vault_id', $vault->id)->where('path', $path)->first(['id', 'created_at']);

        if ($existing === null) {
            $id = DB::table('notes')->insertGetId([...$attributes, 'vault_id' => $vault->id, 'path' => $path, 'created_at' => $now]);
            $createdAt = $now;
        } else {
            $id = Row::int($existing, 'id');
            $createdAt = Row::string($existing, 'created_at');

            DB::table('notes')->where('id', $id)->update($attributes);
            DB::table('notes_fts')->where('rowid', $id)->delete();
            DB::table('note_links')->where('source_note_id', $id)->delete();
            DB::table('note_tags')->where('note_id', $id)->delete();
            DB::table('note_tasks')->where('note_id', $id)->delete();
        }

        DB::table('notes_fts')->insert([
            'rowid' => $id,
            'title' => $parsed->title,
            'body' => $parsed->body,
        ]);

        DB::table('note_tags')->insert(array_map(fn (string $tag): array => [
            'vault_id' => $vault->id,
            'note_id' => $id,
            'name' => $tag,
        ], $parsed->tags));

        foreach (array_chunk($parsed->tasks, 200) as $tasks) {
            DB::table('note_tasks')->insert(array_map(fn (array $task): array => [
                'vault_id' => $vault->id,
                'note_id' => $id,
                ...$task,
            ], $tasks));
        }

        $note = new Note()->newFromBuilder([
            ...$attributes,
            'id' => $id,
            'vault_id' => $vault->id,
            'path' => $path,
            'created_at' => $createdAt,
        ]);

        $note->wasRecentlyCreated = $existing === null;
        $note->setRelation('vault', $vault);

        return $note;
    }

    /**
     * @param  list<WikiLink>  $links
     * @param  array<string, int>  $resolved
     * @return list<array<string, mixed>>
     */
    public function linkRows(Vault $vault, int $noteId, array $links, array $resolved): array
    {
        return array_map(fn (WikiLink $link): array => [
            'vault_id' => $vault->id,
            'source_note_id' => $noteId,
            'target_note_id' => $resolved[$link->targetKey] ?? null,
            'target' => $link->target,
            'target_key' => $link->targetKey,
            'heading' => $link->heading,
            'alias' => $link->alias,
            'is_embed' => $link->isEmbed,
            'line' => $link->line,
        ], $links);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function insertLinks(array $rows): void
    {
        foreach (array_chunk($rows, self::LINK_INSERT_CHUNK) as $chunk) {
            DB::table('note_links')->insert($chunk);
        }
    }
}
