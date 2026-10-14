<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\TableView;
use App\Services\ColourGroupQuery;
use App\Services\ColourGroups;
use App\Services\Row;
use App\Services\Templates;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ListTableRows
{
    public function __construct(
        private ColourGroups $notes,
        private Templates $templates,
    ) {
        //
    }

    /**
     * The notes a view's query matches (every note when it has none), with their frontmatter,
     * and the property keys those notes use, most common first, to offer as columns.
     *
     * @return array{rows: list<array{id: int, title: string, path: string, modified_at: int, properties: array<mixed>}>, keys: list<string>}
     */
    public function handle(TableView $view): array
    {
        try {
            $query = mb_trim($view->query) === '' ? null : ColourGroupQuery::parse($view->query);
        } catch (InvalidArgumentException) {
            $query = null;
        }

        $modified = [];

        foreach (DB::table('notes')->where('vault_id', $view->vault_id)->get(['id', 'modified_at']) as $row) {
            $modified[Row::int($row, 'id')] = Row::int($row, 'modified_at');
        }

        $rows = [];
        $keys = [];

        foreach ($this->notes->notes($view->vault) as $id => $note) {
            if ($this->templates->contains($note['path']) || ($query instanceof ColourGroupQuery && ! $query->matches($note))) {
                continue;
            }

            foreach (array_keys($note['frontmatter']) as $key) {
                $keys[(string) $key] = ($keys[(string) $key] ?? 0) + 1;
            }

            $rows[] = [
                'id' => $id,
                'title' => $note['title'],
                'path' => $note['path'],
                'modified_at' => $modified[$id] ?? 0,
                'properties' => $note['frontmatter'],
            ];
        }

        usort($rows, fn (array $a, array $b): int => strnatcasecmp($a['title'], $b['title']));
        arsort($keys);

        return ['rows' => $rows, 'keys' => array_map(strval(...), array_keys($keys))];
    }
}
