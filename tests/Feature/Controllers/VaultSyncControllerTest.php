<?php

declare(strict_types=1);

use App\Services\IndexingProgress;

it('syncs the vault index with the folder', function (): void {
    $vault = vaultWithFiles(['A.md' => 'a']);

    $this->postJson(route('vault-sync.store', $vault))
        ->assertOk()
        ->assertExactJson(['added' => 1, 'updated' => 0, 'removed' => 0, 'moved' => 0, 'attachments' => 0, 'changed' => true, 'indexing' => null]);

    $this->postJson(route('vault-sync.store', $vault))
        ->assertExactJson(['added' => 0, 'updated' => 0, 'removed' => 0, 'moved' => 0, 'attachments' => 0, 'changed' => false, 'indexing' => null]);
});

it('reports attachment changes', function (): void {
    $vault = vaultWithFiles(['A.md' => 'a']);

    $this->postJson(route('vault-sync.store', $vault));

    file_put_contents($vault->path.'/photo.png', 'png');

    $this->postJson(route('vault-sync.store', $vault))
        ->assertExactJson(['added' => 0, 'updated' => 0, 'removed' => 0, 'moved' => 0, 'attachments' => 1, 'changed' => true, 'indexing' => null]);
});

it('does not sync while a background index is running', function (): void {
    $vault = vaultWithFiles(['A.md' => 'a']);

    resolve(IndexingProgress::class)->update($vault, 10, 40);

    $this->postJson(route('vault-sync.store', $vault))
        ->assertExactJson([
            'added' => 0, 'updated' => 0, 'removed' => 0, 'moved' => 0, 'attachments' => 0, 'changed' => false,
            'indexing' => ['done' => 10, 'total' => 40],
        ]);

    expect($vault->notes()->count())->toBe(0);
});
