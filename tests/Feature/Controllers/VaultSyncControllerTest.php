<?php

declare(strict_types=1);

it('syncs the vault index with the folder', function (): void {
    $vault = vaultWithFiles(['A.md' => 'a']);

    $this->postJson(route('vault-sync.store', $vault))
        ->assertOk()
        ->assertExactJson(['added' => 1, 'updated' => 0, 'removed' => 0, 'changed' => true]);

    $this->postJson(route('vault-sync.store', $vault))
        ->assertExactJson(['added' => 0, 'updated' => 0, 'removed' => 0, 'changed' => false]);
});
