<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Services\Row;
use App\Services\Templates;
use Illuminate\Support\Facades\DB;

final readonly class BuildLocalGraph
{
    public const int MAX_DEPTH = 3;

    /** More notes than this make the side panel's small graph unreadable, so it stops growing. */
    public const int MAX_NOTES = 300;

    public function __construct(private Templates $templates)
    {
        //
    }

    /**
     * The notes within `$depth` links of a note, either way round, and the links between them.
     * Unresolved links from those notes are shown as ghosts but not followed.
     *
     * @return array{
     *     nodes: list<array{id: string, note_id: int|null, title: string, path: string|null, depth: int, degree: int, ghost: bool}>,
     *     edges: list<array{source: string, target: string}>,
     *     truncated: bool
     * }
     */
    public function handle(Note $note, int $depth): array
    {
        $depth = max(1, min(self::MAX_DEPTH, $depth));
        $distance = [$note->id => 0];
        $frontier = [$note->id];
        $truncated = false;

        for ($step = 1; $step <= $depth && $frontier !== [] && ! $truncated; $step++) {
            $next = [];

            foreach ($this->neighbours($note->vault_id, $frontier) as $id) {
                if (isset($distance[$id])) {
                    continue;
                }

                if (count($distance) >= self::MAX_NOTES) {
                    $truncated = true;

                    break;
                }

                $distance[$id] = $step;
                $next[] = $id;
            }

            $frontier = $next;
        }

        return [...$this->graph($note->vault_id, $distance), 'truncated' => $truncated];
    }

    /**
     * Notes linked to or from any of the given notes, templates left out.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function neighbours(int $vaultId, array $ids): array
    {
        $linked = [];

        foreach (array_chunk($ids, 500) as $chunk) {
            foreach (DB::table('note_links')->where('vault_id', $vaultId)->whereIn('source_note_id', $chunk)->whereNotNull('target_note_id')->get(['target_note_id']) as $row) {
                $linked[Row::int($row, 'target_note_id')] = true;
            }

            foreach (DB::table('note_links')->where('vault_id', $vaultId)->whereIn('target_note_id', $chunk)->get(['source_note_id']) as $row) {
                $linked[Row::int($row, 'source_note_id')] = true;
            }
        }

        if ($linked === []) {
            return [];
        }

        return array_values($this->templates
            ->exclude(DB::table('notes')->where('vault_id', $vaultId)->whereIn('id', array_keys($linked)))
            ->orderBy('path')
            ->get(['id'])
            ->map(fn (object $row): int => Row::int($row, 'id'))
            ->all());
    }

    /**
     * @param  array<int, int>  $distance  note id => links away
     * @return array{nodes: list<array{id: string, note_id: int|null, title: string, path: string|null, depth: int, degree: int, ghost: bool}>, edges: list<array{source: string, target: string}>}
     */
    private function graph(int $vaultId, array $distance): array
    {
        $ids = array_keys($distance);
        $nodes = [];

        foreach (DB::table('notes')->whereIn('id', $ids)->get(['id', 'title', 'path']) as $row) {
            $id = Row::int($row, 'id');

            $nodes['n'.$id] = ['id' => 'n'.$id, 'note_id' => $id, 'title' => Row::string($row, 'title'), 'path' => Row::string($row, 'path'), 'depth' => $distance[$id], 'degree' => 0, 'ghost' => false];
        }

        $edges = [];
        $degrees = [];

        foreach (array_chunk($ids, 500) as $chunk) {
            $links = DB::table('note_links')->where('vault_id', $vaultId)->whereIn('source_note_id', $chunk)->get(['source_note_id', 'target_note_id', 'target', 'target_key']);

            foreach ($links as $link) {
                $source = 'n'.Row::int($link, 'source_note_id');
                $targetNoteId = Row::nullableInt($link, 'target_note_id');

                if ($targetNoteId !== null && ! isset($distance[$targetNoteId])) {
                    continue;
                }

                $target = $targetNoteId === null ? 'g'.Row::string($link, 'target_key') : 'n'.$targetNoteId;

                if ($targetNoteId === null) {
                    $nodes[$target] ??= ['id' => $target, 'note_id' => null, 'title' => Row::string($link, 'target'), 'path' => null, 'depth' => $nodes[$source]['depth'] + 1, 'degree' => 0, 'ghost' => true];
                }

                $key = strcmp($source, $target) < 0 ? "{$source}|{$target}" : "{$target}|{$source}";

                if ($source === $target || isset($edges[$key])) {
                    continue;
                }

                $edges[$key] = ['source' => $source, 'target' => $target];
                $degrees[$source] = ($degrees[$source] ?? 0) + 1;
                $degrees[$target] = ($degrees[$target] ?? 0) + 1;
            }
        }

        return [
            'nodes' => array_values(array_map(fn (array $node): array => [...$node, 'degree' => $degrees[$node['id']] ?? 0], $nodes)),
            'edges' => array_values($edges),
        ];
    }
}
