<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Models\NoteLink;
use App\Models\Vault;

final readonly class BuildGraph
{
    /**
     * Build the vault's note graph. Unresolved links become "ghost" nodes keyed by their target.
     *
     * @return array{
     *     nodes: list<array{id: string, note_id: int|null, title: string, path: string|null, folder: string, degree: int, ghost: bool}>,
     *     edges: list<array{source: string, target: string}>
     * }
     */
    public function handle(Vault $vault): array
    {
        $nodes = [];

        foreach (Note::query()->where('vault_id', $vault->id)->orderBy('id')->get(['id', 'title', 'path']) as $note) {
            $nodes['n'.$note->id] = [
                'id' => 'n'.$note->id,
                'note_id' => $note->id,
                'title' => $note->title,
                'path' => $note->path,
                'folder' => str_contains($note->path, '/') ? dirname($note->path) : '',
                'degree' => 0,
                'ghost' => false,
            ];
        }

        $edges = [];

        $degrees = [];

        $links = NoteLink::query()
            ->where('vault_id', $vault->id)
            ->orderBy('id')
            ->get(['source_note_id', 'target_note_id', 'target', 'target_key']);

        foreach ($links as $link) {
            $source = 'n'.$link->source_note_id;

            if ($link->target_note_id === null) {
                $target = 'g'.$link->target_key;

                $nodes[$target] ??= [
                    'id' => $target,
                    'note_id' => null,
                    'title' => $link->target,
                    'path' => null,
                    'folder' => '',
                    'degree' => 0,
                    'ghost' => true,
                ];
            } else {
                $target = 'n'.$link->target_note_id;
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
