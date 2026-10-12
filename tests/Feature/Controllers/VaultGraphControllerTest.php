<?php

declare(strict_types=1);

use App\Actions\IndexVault;

it('shows the vault graph', function (): void {
    $vault = vaultWithFiles(['A.md' => '[[B]]', 'B.md' => '# B']);
    resolve(IndexVault::class)->handle($vault);

    $this->get(route('vault-graph.show', $vault))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('graph/show')
            ->where('vault.id', $vault->id)
            ->has('tree', 2)
            ->has('graph.nodes', 2)
            ->has('graph.edges', 1));
});
