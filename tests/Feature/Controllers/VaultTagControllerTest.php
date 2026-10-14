<?php

declare(strict_types=1);

use App\Actions\IndexVault;

it('shows notes for a nested tag', function (): void {
    $vault = vaultWithFiles(['A.md' => '#project/kite', 'B.md' => '#other']);
    resolve(IndexVault::class)->handle($vault);

    $this->get(route('vault-tags.show', ['vault' => $vault, 'tag' => 'Project/Kite']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tag/show')
            ->where('tag', 'project/kite')
            ->has('notes', 1)
            ->where('notes.0.path', 'A.md')
            ->has('tree', 2));
});
