<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Vault;
use App\Services\Row;
use Illuminate\Support\Facades\DB;

final readonly class ListTasks
{
    /**
     * Every checklist item in the vault, with its note and the note's tags, open ones first and
     * by due date, then by note and line.
     *
     * @return list<array{id: int, note_id: int, title: string, path: string, line: int, text: string, done: bool, due: string|null, tags: list<string>}>
     */
    public function handle(Vault $vault): array
    {
        $rows = DB::table('note_tasks')
            ->join('notes', 'notes.id', '=', 'note_tasks.note_id')
            ->where('note_tasks.vault_id', $vault->id)
            ->orderBy('note_tasks.done')
            ->orderByRaw('note_tasks.due IS NULL, note_tasks.due')
            ->orderByRaw('notes.path COLLATE NOCASE')
            ->orderBy('note_tasks.line')
            ->get(['note_tasks.id', 'note_tasks.note_id', 'notes.title', 'notes.path', 'note_tasks.line', 'note_tasks.text', 'note_tasks.done', 'note_tasks.due']);

        /** @var array<int, list<string>> $tags */
        $tags = [];

        foreach (DB::table('note_tags')->where('vault_id', $vault->id)->whereIn('note_id', $rows->pluck('note_id')->unique()->all())->orderBy('name')->get(['note_id', 'name']) as $tag) {
            $tags[Row::int($tag, 'note_id')][] = Row::string($tag, 'name');
        }

        return array_values($rows->map(fn (object $row): array => [
            'id' => Row::int($row, 'id'),
            'note_id' => Row::int($row, 'note_id'),
            'title' => Row::string($row, 'title'),
            'path' => Row::string($row, 'path'),
            'line' => Row::int($row, 'line'),
            'text' => Row::string($row, 'text'),
            'done' => (bool) $row->done,
            'due' => is_string($row->due) ? mb_substr($row->due, 0, 10) : null,
            'tags' => $tags[Row::int($row, 'note_id')] ?? [],
        ])->all());
    }
}
