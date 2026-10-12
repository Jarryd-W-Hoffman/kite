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
        ->and($results[0]['snippet'])->toBe('<mark>Gardening</mark> <mark>Tomatoes</mark> &lt;b&gt;need&lt;/b&gt; sun.')
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
