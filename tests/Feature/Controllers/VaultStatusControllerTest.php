<?php

declare(strict_types=1);

use App\Services\IndexingProgress;

it('reports indexing progress', function (): void {
    $vault = vaultWithFiles();

    $this->getJson(route('vault-status.show', $vault))->assertExactJson(['indexing' => null]);

    resolve(IndexingProgress::class)->update($vault, 5, 20);

    $this->getJson(route('vault-status.show', $vault))->assertExactJson(['indexing' => ['done' => 5, 'total' => 20]]);
});
