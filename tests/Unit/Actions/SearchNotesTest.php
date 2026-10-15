<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Actions\SearchNotes;
use App\Models\Note;

it('ranks title matches above body matches and highlights snippets safely', function (): void {
    $vault = vaultWithFiles([
        'Gardening.md' => "# Gardening\nTomatoes <b>need</b> sun.",
        'Cooking.md' => "# Cooking\nRoast the gardening tomatoes\n\nwith   care.",
        'Other.md' => '# Other',
    ]);

    resolve(IndexVault::class)->handle($vault);

    $results = resolve(SearchNotes::class)->handle($vault, 'garden tomato');

    expect(array_column($results, 'title'))->toBe(['Gardening', 'Cooking'])
        ->and($results[0]['snippet'])->toBe('<mark>Tomatoes</mark> &lt;b&gt;need&lt;/b&gt; sun.')
        ->and($results[1])->toMatchArray([
            'id' => Note::query()->where('path', 'Cooking.md')->value('id'),
            'path' => 'Cooking.md',
        ]);
});

it('only searches the given vault and respects the limit', function (): void {
    $first = vaultWithFiles(['A.md' => 'shared word', 'B.md' => 'shared word']);
    $second = vaultWithFiles(['C.md' => 'shared word']);

    resolve(IndexVault::class)->handle($first);
    resolve(IndexVault::class)->handle($second);

    expect(resolve(SearchNotes::class)->handle($second, 'shared'))->toHaveCount(1)
        ->and(resolve(SearchNotes::class)->handle($first, 'shared', limit: 1))->toHaveCount(1);
});

it('returns recently modified notes for an empty query', function (): void {
    $vault = vaultWithFiles(['Old.md' => 'old', 'New.md' => 'new']);

    touch($vault->path.'/Old.md', time() - 100);

    resolve(IndexVault::class)->handle($vault);

    expect(resolve(SearchNotes::class)->handle($vault, '  "*  '))->toBe([
        ['id' => Note::query()->where('path', 'New.md')->value('id'), 'title' => 'New', 'path' => 'New.md', 'snippet' => ''],
        ['id' => Note::query()->where('path', 'Old.md')->value('id'), 'title' => 'Old', 'path' => 'Old.md', 'snippet' => ''],
    ]);
});

it('treats fts syntax in queries as plain text', function (): void {
    $vault = vaultWithFiles(['A.md' => 'alpha OR beta NEAR gamma']);

    resolve(IndexVault::class)->handle($vault);

    expect(resolve(SearchNotes::class)->handle($vault, 'alpha AND "beta" (NOT gamma)'))->toBe([]);
    expect(resolve(SearchNotes::class)->handle($vault, 'alpha "beta" (gamma)'))->toHaveCount(1);
});

it('keeps the snippet when the title is all there is, and leaves titles mid-snippet alone', function (): void {
    $vault = vaultWithFiles(['Only.md' => '# Only', 'Mid.md' => "# Mid\n\nThe Mid point"]);
    resolve(IndexVault::class)->handle($vault);
    $search = resolve(SearchNotes::class);

    expect($search->handle($vault, 'only')[0]['snippet'])->toBe('<mark>Only</mark>')
        ->and($search->handle($vault, 'point')[0]['snippet'])->toBe('The Mid <mark>point</mark>');
});

it('narrows a search with filters', function (): void {
    $vault = vaultWithFiles([
        'Work/Standup.md' => "---\nstatus: active\n---\n# Standup #work\n\nDaily sync notes",
        'Work/Old standup.md' => "# Old standup #work #archived\n\nsync",
        'Home/Groceries.md' => "# Groceries\n\nsync the list",
        'Templates/Sync.md' => "# {{title}} #work\n\nsync",
        'Links.md' => "# Links\n\nSee https://example.com for more",
    ]);
    resolve(IndexVault::class)->handle($vault);
    $titles = fn (string $query): array => array_column(resolve(SearchNotes::class)->handle($vault, $query), 'title');

    expect($titles('sync tag:work'))->toEqualCanonicalizing(['Standup', 'Old standup'])
        ->and($titles('sync tag:work -tag:archived'))->toBe(['Standup'])
        ->and($titles('folder:Home'))->toBe(['Groceries'])
        ->and($titles('status:active'))->toBe(['Standup'])
        ->and($titles('sync tag:nothing'))->toBe([])
        ->and($titles('sync tag:'))->toEqualCanonicalizing(['Standup', 'Old standup', 'Groceries', '{{title}}'])
        ->and($titles('https://example.com'))->toBe(['Links'])
        ->and($titles('links tag:""'))->toBe([]);
});
