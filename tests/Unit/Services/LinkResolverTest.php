<?php

declare(strict_types=1);

use App\Models\Note;
use App\Models\Vault;
use App\Services\LinkResolver;

it('prefers the match closest to the vault root, then alphabetical', function (): void {
    $vault = Vault::factory()->create();

    $deep = Note::factory()->for($vault)->create(['path' => 'z/y/Idea.md', 'path_key' => 'z/y/idea', 'slug' => 'idea']);
    $shallowB = Note::factory()->for($vault)->create(['path' => 'b/Idea.md', 'path_key' => 'b/idea', 'slug' => 'idea']);
    Note::factory()->for($vault)->create(['path' => 'c/Idea.md', 'path_key' => 'c/idea', 'slug' => 'idea']);
    $exact = Note::factory()->for($vault)->create(['path' => 'y/Idea.md', 'path_key' => 'y/idea', 'slug' => 'idea']);
    Note::factory()->for($vault)->create(['path' => 'under_score/x.md', 'path_key' => 'under_score/x', 'slug' => 'x']);

    expect(new LinkResolver()->resolve($vault, ['idea', 'y/idea', 'z/y/idea', 'under%score/x', 'nope', 'idea']))->toBe([
        'idea' => $shallowB->id,
        'y/idea' => $exact->id,
        'z/y/idea' => $deep->id,
    ]);
});

it('returns nothing for no keys', function (): void {
    expect(new LinkResolver()->resolve(Vault::factory()->create(), []))->toBe([]);
});

it('resolves large key sets against an in-memory index with the same precedence', function (): void {
    $vault = Vault::factory()->create();

    $deep = Note::factory()->for($vault)->create(['path' => 'z/y/Idea.md', 'path_key' => 'z/y/idea', 'slug' => 'idea']);
    $shallow = Note::factory()->for($vault)->create(['path' => 'b/Idea.md', 'path_key' => 'b/idea', 'slug' => 'idea']);
    Note::factory()->for($vault)->create(['path' => 'c/Idea.md', 'path_key' => 'c/idea', 'slug' => 'idea']);

    $keys = ['idea', 'y/idea', 'z/y/idea', 'b/idea', ...array_map(fn (int $i): string => "missing {$i}", range(1, 70))];

    expect(new LinkResolver()->resolve($vault, $keys))->toBe([
        'idea' => $shallow->id,
        'y/idea' => $deep->id,
        'z/y/idea' => $deep->id,
        'b/idea' => $shallow->id,
    ]);
});
