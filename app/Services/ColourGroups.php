<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ColourGroup;
use App\Models\Vault;
use Generator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Matches a vault's notes against its colour groups. Matching runs on the server so the graph, the
 * file tree and anything else agree on which group a note belongs to.
 */
final readonly class ColourGroups
{
    /**
     * Map each note to the first group it matches. Notes matching no group are left out, and
     * vaults without groups skip the work entirely.
     *
     * @return array<int, int> note id => colour group id
     */
    public function assign(Vault $vault): array
    {
        $queries = [];

        foreach ($vault->colourGroups()->get(['id', 'query']) as $group) {
            try {
                $queries[$group->id] = ColourGroupQuery::parse($group->query);
            } catch (InvalidArgumentException) {
                // Saved queries are validated; skip one that no longer parses rather than fail.
            }
        }

        if ($queries === []) {
            return [];
        }

        $assigned = [];

        foreach ($this->notes($vault) as $id => $note) {
            foreach ($queries as $groupId => $query) {
                if ($query->matches($note)) {
                    $assigned[$id] = $groupId;

                    break;
                }
            }
        }

        return $assigned;
    }

    /**
     * How many notes in the vault a query matches, for the live count in the group editor.
     */
    public function count(Vault $vault, ColourGroupQuery $query): int
    {
        return $query->applyTo(DB::table('notes')->where('vault_id', $vault->id))->count();
    }

    /**
     * @return list<array{id: int, name: string, query: string, colour: string}>
     */
    public function summaries(Vault $vault): array
    {
        return array_values($vault->colourGroups()->get()->map(fn (ColourGroup $group): array => $group->toSummary())->all());
    }

    /**
     * @return Generator<int, array{title: string, path: string, tags: list<string>, frontmatter: array<mixed>}>
     */
    public function notes(Vault $vault): Generator
    {
        $tags = [];

        foreach (DB::table('note_tags')->where('vault_id', $vault->id)->get(['note_id', 'name']) as $row) {
            $tags[Row::int($row, 'note_id')][] = Row::string($row, 'name');
        }

        foreach (DB::table('notes')->where('vault_id', $vault->id)->get(['id', 'title', 'path', 'frontmatter']) as $row) {
            $id = Row::int($row, 'id');
            $frontmatter = json_decode(Row::string($row, 'frontmatter'), true);

            yield $id => [
                'title' => Row::string($row, 'title'),
                'path' => Row::string($row, 'path'),
                'tags' => $tags[$id] ?? [],
                'frontmatter' => is_array($frontmatter) ? $frontmatter : [],
            ];
        }
    }
}
