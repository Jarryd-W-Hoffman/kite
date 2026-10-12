<?php

declare(strict_types=1);

use App\Actions\IndexVault;

it('searches notes in the vault', function (): void {
    $vault = vaultWithFiles(['Kite.md' => '# Kite', 'Other.md' => 'other']);
    resolve(IndexVault::class)->handle($vault);

    $this->getJson(route('vault-search.index', ['vault' => $vault, 'q' => 'kit']))
        ->assertOk()
        ->assertJsonCount(1, 'results')
        ->assertJsonPath('results.0.title', 'Kite');

    $this->getJson(route('vault-search.index', $vault))
        ->assertJsonCount(2, 'results');
});
