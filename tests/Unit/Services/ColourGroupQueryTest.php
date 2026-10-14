<?php

declare(strict_types=1);

use App\Services\ColourGroupQuery;

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

it('matches notes against each kind of term', function (string $query, bool $expected): void {
    expect(ColourGroupQuery::parse($query)->matches(groupNote()))->toBe($expected);
})->with([
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

it('treats false frontmatter values as text', function (): void {
    expect(ColourGroupQuery::parse('draft:false')->matches(groupNote(['frontmatter' => ['draft' => false]])))->toBeTrue();
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
