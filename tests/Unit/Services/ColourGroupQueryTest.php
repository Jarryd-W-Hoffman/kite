<?php

declare(strict_types=1);

use App\Models\Vault;
use App\Services\ColourGroupQuery;
use Illuminate\Support\Facades\DB;

function groupNote(array $overrides = []): array
{
    return [
        'title' => 'Weekly Meeting',
        'path' => 'Work/Projects/Weekly Meeting.md',
        'tags' => ['project', 'ideas/later'],
        'frontmatter' => ['Category' => 'Person', 'aliases' => ['Standup', 'Sync'], 'draft' => true, 'priority' => 2, 'meta' => ['nested' => 'x']],
        ...$overrides,
    ];
}

dataset('group queries', [
    'tag' => ['tag:project', true],
    'tag with hash and case' => ['tag:#PROJECT', true],
    'parent tag matches nested' => ['tag:ideas', true],
    'nested tag' => ['tag:ideas/later', true],
    'partial tag does not match' => ['tag:proj', false],
    'folder' => ['folder:Work', true],
    'nested folder with slashes' => ['folder:/work/projects/', true],
    'other folder' => ['folder:Personal', false],
    'folder prefix is not a folder' => ['folder:Wor', false],
    'path text' => ['path:projects/weekly', true],
    'title text' => ['title:meeting', true],
    'bare word searches the title' => ['weekly', true],
    'quoted phrase' => ['"weekly meeting"', true],
    'quoted field value' => ['folder:"Work/Projects"', true],
    'frontmatter field ignores case' => ['category:person', true],
    'frontmatter list item' => ['aliases:sync', true],
    'frontmatter boolean' => ['draft:true', true],
    'frontmatter number' => ['priority:2', true],
    'frontmatter wildcard' => ['category:*', true],
    'missing frontmatter field' => ['status:*', false],
    'frontmatter value mismatch' => ['category:place', false],
    'non-scalar frontmatter value' => ['meta:x', false],
    'every term must match' => ['tag:project folder:Personal', false],
    'all terms match' => ['tag:project folder:Work category:person', true],
    'negated term' => ['-tag:project', false],
    'negated miss' => ['tag:project -tag:archived', true],
]);

/**
 * Store a note in the index so a query can be run against it in the database.
 *
 * @param  array{title: string, path: string, tags: list<string>, frontmatter: array<mixed>}  $note
 */
function storeGroupNote(array $note): int
{
    $vault = Vault::factory()->create();
    $id = DB::table('notes')->insertGetId([
        'vault_id' => $vault->id,
        'path' => $note['path'],
        'path_key' => mb_strtolower($note['path']),
        'slug' => mb_strtolower(basename($note['path'])),
        'title' => $note['title'],
        'frontmatter' => json_encode($note['frontmatter']),
        'word_count' => 0,
        'size' => 0,
        'modified_at' => 0,
        'hash' => '',
    ]);

    DB::table('note_tags')->insert(array_map(fn (string $tag): array => ['vault_id' => $vault->id, 'note_id' => $id, 'name' => $tag], $note['tags']));

    return $id;
}

function matchesInDatabase(string $query, array $note): bool
{
    $id = storeGroupNote($note);

    return ColourGroupQuery::parse($query)->applyTo(DB::table('notes')->where('id', $id))->exists();
}

it('matches notes against each kind of term', function (string $query, bool $expected): void {
    expect(ColourGroupQuery::parse($query)->matches(groupNote()))->toBe($expected);
})->with('group queries');

it('matches the same notes in the database', function (string $query, bool $expected): void {
    expect(matchesInDatabase($query, groupNote()))->toBe($expected);
})->with('group queries');

it('ignores case beyond ASCII in both', function (string $query, bool $expected): void {
    $note = groupNote(['title' => 'Été Plans', 'path' => 'Été/Ölçek.md', 'frontmatter' => ['Statut' => 'Prêt']]);

    expect(ColourGroupQuery::parse($query)->matches($note))->toBe($expected)
        ->and(matchesInDatabase($query, $note))->toBe($expected);
})->with([
    'folder' => ['folder:ÉTÉ', true],
    'path' => ['path:ÖLÇEK', true],
    'title' => ['ÉTÉ', true],
    'field' => ['statut:PRÊT', true],
    'field type name is not a value' => ['statut:text', false],
    'boolean type name is not a value' => ['statut:true', false],
]);

it('treats false frontmatter values as text', function (): void {
    $note = groupNote(['frontmatter' => ['draft' => false, 'scores' => [1.5, false]]]);

    expect(ColourGroupQuery::parse('draft:false')->matches($note))->toBeTrue()
        ->and(matchesInDatabase('draft:false', $note))->toBeTrue()
        ->and(matchesInDatabase('scores:1.5', $note))->toBeTrue()
        ->and(matchesInDatabase('scores:false', $note))->toBeTrue()
        ->and(matchesInDatabase('draft:0', $note))->toBeFalse();
});

it('rejects empty queries and terms without a value', function (string $query): void {
    expect(fn (): ColourGroupQuery => ColourGroupQuery::parse($query))->toThrow(InvalidArgumentException::class);
})->with([
    'empty' => [''],
    'whitespace' => ['   '],
    'bare field' => ['tag:'],
    'empty quotes' => ['tag:""'],
    'bare field after a term' => ['folder:Work category:'],
]);
