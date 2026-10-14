<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Vault;
use App\Services\Row;
use Illuminate\Support\Facades\DB;

final readonly class BuildGraph
{
    /**
     * Build the vault's note graph. Unresolved links become "ghost" nodes keyed by their target.
     *
     * @return array{
     *     nodes: list<array{id: string, note_id: int|null, title: string, path: string|null, folder: string, tags: list<string>, degree: int, ghost: bool}>,
     *     edges: list<array{source: string, target: string}>
     * }
     */
    public function handle(Vault $vault): array
    {
        $nodes = [];
        $tags = [];

        foreach (DB::table('note_tags')->where('vault_id', $vault->id)->orderBy('name')->get(['note_id', 'name']) as $row) {
            $tags[Row::int($row, 'note_id')][] = Row::string($row, 'name');
        }

        foreach (DB::table('notes')->where('vault_id', $vault->id)->orderBy('id')->get(['id', 'title', 'path']) as $row) {
            $id = Row::int($row, 'id');
            $path = Row::string($row, 'path');

            $nodes['n'.$id] = [
                'id' => 'n'.$id,
                'note_id' => $id,
                'title' => Row::string($row, 'title'),
                'path' => $path,
                'folder' => str_contains($path, '/') ? dirname($path) : '',
                'tags' => $tags[$id] ?? [],
                'degree' => 0,
                'ghost' => false,
            ];
        }

        $edges = [];

        $degrees = [];

        $links = DB::table('note_links')
            ->where('vault_id', $vault->id)
            ->orderBy('id')
            ->get(['source_note_id', 'target_note_id', 'target', 'target_key']);

        foreach ($links as $link) {
            $source = 'n'.Row::int($link, 'source_note_id');
            $targetNoteId = Row::nullableInt($link, 'target_note_id');

            if ($targetNoteId === null) {
                $target = 'g'.Row::string($link, 'target_key');

                $nodes[$target] ??= [
                    'id' => $target,
                    'note_id' => null,
                    'title' => Row::string($link, 'target'),
                    'path' => null,
                    'folder' => '',
                    'tags' => [],
                    'degree' => 0,
                    'ghost' => true,
                ];
            } else {
                $target = 'n'.$targetNoteId;
            }

            if ($source === $target) {
                continue;
            }

            $key = strcmp($source, $target) < 0 ? "{$source}|{$target}" : "{$target}|{$source}";

            if (isset($edges[$key])) {
                continue;
            }

            $edges[$key] = ['source' => $source, 'target' => $target];
            $degrees[$source] = ($degrees[$source] ?? 0) + 1;
            $degrees[$target] = ($degrees[$target] ?? 0) + 1;
        }

        return [
            'nodes' => array_values(array_map(fn (array $node): array => [
                ...$node,
                'degree' => $degrees[$node['id']] ?? 0,
            ], $nodes)),
            'edges' => array_values($edges),
        ];
    }
}
