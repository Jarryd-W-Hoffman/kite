<?php

declare(strict_types=1);

use App\Actions\BuildLocalGraph;
use App\Actions\IndexVault;
use App\Models\Note;
use Illuminate\Support\Collection;

beforeEach(function (): void {
    // A chain A - B - C - D - E, with A also linking to a missing note and to a template.
    $this->vault = vaultWithFiles([
        'Work/A.md' => "# A #project\n\n[[B]] [[Ghost]] [[Tpl]]",
        'Work/B.md' => "---\nstatus: active\n---\n# B\n\n[[C]]",
        'C.md' => "# C\n\n[[D]]",
        'D.md' => "# D\n\n[[E]] [[Other ghost]]",
        'E.md' => '# E',
        'Lonely.md' => '# Lonely',
        'Templates/Tpl.md' => '# Tpl [[A]]',
    ]);
    resolve(IndexVault::class)->handle($this->vault);
    $this->note = fn (string $title): Note => Note::query()->where('title', $title)->sole();
});

it('shows only the notes a filter matches, with their links and missing notes', function (): void {
    $this->get(route('vault-graph.show', [$this->vault, 'filter' => 'folder:Work']))
        ->assertInertia(fn ($page) => $page
            ->where('filter', 'folder:Work')
            ->where('filter_error', null)
            ->where('graph.nodes', fn (Collection $nodes): bool => $nodes->pluck('title')->sort()->values()->all() === ['A', 'B', 'Ghost'])
            ->where('graph.edges', fn (Collection $edges): bool => $edges->count() === 2));

    $this->get(route('vault-graph.show', [$this->vault, 'filter' => 'status:active -tag:project']))
        ->assertInertia(fn ($page) => $page->where('graph.nodes', fn (Collection $nodes): bool => $nodes->pluck('title')->all() === ['B'])->where('graph.nodes.0.degree', 0));
});

it('shows the whole graph and the problem when a filter doesn’t parse', function (): void {
    $this->get(route('vault-graph.show', [$this->vault, 'filter' => 'tag:']))
        ->assertInertia(fn ($page) => $page
            ->where('filter_error', 'Every term in a colour group query needs a value.')
            ->where('graph.nodes', fn (Collection $nodes): bool => $nodes->where('ghost', false)->count() === 6));

    $this->get(route('vault-graph.show', $this->vault))
        ->assertInertia(fn ($page) => $page->where('filter', '')->where('filter_error', null));
});

it('reaches further out with each step of depth', function (int $depth, array $titles): void {
    $graph = $this->getJson(route('note-graphs.show', [$this->vault, ($this->note)('B'), 'depth' => $depth]))->assertOk()->json();

    expect(collect($graph['nodes'])->where('ghost', false)->pluck('title')->sort()->values()->all())->toBe($titles)
        ->and($graph['truncated'])->toBeFalse()
        ->and(collect($graph['nodes'])->firstWhere('title', 'B')['depth'])->toBe(0);
})->with([
    'one link away' => [1, ['A', 'B', 'C']],
    'two links away' => [2, ['A', 'B', 'C', 'D']],
    'three links away' => [3, ['A', 'B', 'C', 'D', 'E']],
]);

it('shows missing notes and links between the notes it reaches, but not templates', function (): void {
    $graph = $this->getJson(route('note-graphs.show', [$this->vault, ($this->note)('A'), 'depth' => 1]))->json();

    expect(collect($graph['nodes'])->pluck('title')->sort()->values()->all())->toBe(['A', 'B', 'Ghost'])
        ->and(collect($graph['nodes'])->firstWhere('title', 'Ghost'))->toMatchArray(['ghost' => true, 'note_id' => null, 'depth' => 1])
        ->and(collect($graph['nodes'])->firstWhere('title', 'A')['degree'])->toBe(2)
        ->and($graph['edges'])->toHaveCount(2);
});

it('stops growing at a readable size', function (): void {
    $files = ['Hub.md' => '# Hub '.implode(' ', array_map(fn (int $n): string => "[[N{$n}]]", range(1, BuildLocalGraph::MAX_NOTES + 5)))];

    foreach (range(1, BuildLocalGraph::MAX_NOTES + 5) as $n) {
        $files["N{$n}.md"] = "# N{$n}";
    }

    $vault = vaultWithFiles($files);
    resolve(IndexVault::class)->handle($vault);

    $graph = resolve(BuildLocalGraph::class)->handle(Note::query()->where('vault_id', $vault->id)->where('path', 'Hub.md')->sole(), 1);

    expect($graph['truncated'])->toBeTrue()
        ->and($graph['nodes'])->toHaveCount(BuildLocalGraph::MAX_NOTES);
});

it('asks for a depth it can draw', function (mixed $depth): void {
    $this->getJson(route('note-graphs.show', [$this->vault, ($this->note)('A'), 'depth' => $depth]))->assertJsonValidationErrors('depth');
})->with([0, 4, 'deep']);

it('has an empty neighbourhood for a note with no links', function (): void {
    $graph = $this->getJson(route('note-graphs.show', [$this->vault, ($this->note)('Lonely'), 'depth' => 2]))->json();

    expect($graph)->toBe(['nodes' => [['id' => 'n'.($this->note)('Lonely')->id, 'note_id' => ($this->note)('Lonely')->id, 'title' => 'Lonely', 'path' => 'Lonely.md', 'depth' => 0, 'degree' => 0, 'ghost' => false]], 'edges' => [], 'truncated' => false]);
});
