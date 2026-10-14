<?php

declare(strict_types=1);

use App\Actions\BuildGraph;
use App\Actions\IndexVault;
use App\Models\Note;

it('builds nodes and de-duplicated undirected edges with ghost nodes', function (): void {
    $vault = vaultWithFiles([
        'A.md' => '[[B]] [[B#Again]] [[A]] [[Ghost]] [[ghost]]',
        'Folder/B.md' => '[[A]] #shared #beta',
        'Lonely.md' => 'no links',
    ]);
    resolve(IndexVault::class)->handle($vault);

    $a = Note::query()->where('path', 'A.md')->sole();
    $b = Note::query()->where('path', 'Folder/B.md')->sole();
    $lonely = Note::query()->where('path', 'Lonely.md')->sole();

    $graph = resolve(BuildGraph::class)->handle($vault);

    expect($graph['edges'])->toBe([
        ['source' => "n{$a->id}", 'target' => "n{$b->id}"],
        ['source' => "n{$a->id}", 'target' => 'gghost'],
    ])
        ->and(collect($graph['nodes'])->keyBy('id')->all())->toBe([
            "n{$a->id}" => ['id' => "n{$a->id}", 'note_id' => $a->id, 'title' => 'A', 'path' => 'A.md', 'folder' => '', 'tags' => [], 'degree' => 2, 'ghost' => false],
            "n{$b->id}" => ['id' => "n{$b->id}", 'note_id' => $b->id, 'title' => 'B', 'path' => 'Folder/B.md', 'folder' => 'Folder', 'tags' => ['beta', 'shared'], 'degree' => 1, 'ghost' => false],
            "n{$lonely->id}" => ['id' => "n{$lonely->id}", 'note_id' => $lonely->id, 'title' => 'Lonely', 'path' => 'Lonely.md', 'folder' => '', 'tags' => [], 'degree' => 0, 'ghost' => false],
            'gghost' => ['id' => 'gghost', 'note_id' => null, 'title' => 'Ghost', 'path' => null, 'folder' => '', 'tags' => [], 'degree' => 1, 'ghost' => true],
        ]);
});

it('returns an empty graph for an empty vault', function (): void {
    expect(resolve(BuildGraph::class)->handle(vaultWithFiles()))->toBe(['nodes' => [], 'edges' => []]);
});
