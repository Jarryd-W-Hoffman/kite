<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Services\IndexingProgress;

it('shows indexing progress and reloads when indexing finishes', function (): void {
    $vault = vaultWithFiles(['Home.md' => '# Home']);
    $progress = resolve(IndexingProgress::class);

    $progress->update($vault, 120, 480);

    $page = visitSplit(route('vaults.show', $vault));

    $page->assertSee('Indexing notes…')
        ->assertSee('120 / 480')
        ->assertSee('Indexing '.$vault->name);

    resolve(IndexVault::class)->handle($vault);
    $progress->finish($vault);

    $page->assertDontSee('Indexing notes…')
        ->assertSee('Home')
        ->assertNoJavaScriptErrors();
});
