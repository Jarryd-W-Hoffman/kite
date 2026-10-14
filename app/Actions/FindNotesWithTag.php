<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Vault;
use App\Services\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final readonly class FindNotesWithTag
{
    /**
     * Notes tagged with the tag or any nested tag beneath it (e.g. "project" includes "project/kite").
     *
     * @return list<array{id: int, title: string, path: string, modified_at: int, tags: list<string>}>
     */
    public function handle(Vault $vault, string $tag): array
    {
        $tag = mb_strtolower(mb_trim($tag, " \t#/"));

        if ($tag === '') {
            return [];
        }

        $notes = DB::table('notes')
            ->where('vault_id', $vault->id)
            ->whereIn('id', DB::table('note_tags')
                ->select('note_id')
                ->where('vault_id', $vault->id)
                ->where(fn (Builder $query): Builder => $query
                    ->where('name', $tag)
                    ->orWhere('name', 'like', addcslashes($tag, '%_\\').'/%', '\\')))
            ->latest('modified_at')
            ->orderBy('title')
            ->get(['id', 'title', 'path', 'modified_at']);

        $tags = DB::table('note_tags')
            ->whereIn('note_id', $notes->pluck('id'))
            ->orderBy('name')
            ->get(['note_id', 'name'])
            ->groupBy('note_id');

        return array_values($notes->map(fn (object $note): array => [
            'id' => Row::int($note, 'id'),
            'title' => Row::string($note, 'title'),
            'path' => Row::string($note, 'path'),
            'modified_at' => Row::int($note, 'modified_at'),
            'tags' => array_values(($tags->get(Row::int($note, 'id')) ?? collect())
                ->map(fn (object $row): string => Row::string($row, 'name'))
                ->all()),
        ])->all());
    }
}
