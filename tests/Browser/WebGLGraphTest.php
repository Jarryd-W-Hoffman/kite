<?php

declare(strict_types=1);

use App\Actions\IndexVault;

it('renders large vaults with webgl and small ones with canvas', function (): void {
    $files = [];

    foreach (range(1, 1200) as $number) {
        $files['F'.($number % 12)."/Note {$number}.md"] = '[[Note '.(($number % 1200) + 1).']] [[Note '.(($number * 7 % 1200) + 1).']]';
    }

    $large = vaultWithFiles($files);
    resolve(IndexVault::class)->handle($large);

    $page = visitSplit(route('vault-graph.show', $large))->resize(1440, 900);

    $page->assertDataAttribute('@graph-canvas', 'renderer', 'webgl')
        ->assertSee('1200 notes')
        ->wait(1.5)
        ->assertNoJavaScriptErrors();

    $small = vaultWithFiles(['A.md' => '[[B]]', 'B.md' => '# B']);
    resolve(IndexVault::class)->handle($small);

    visitSplit(route('vault-graph.show', $small))
        ->assertDataAttribute('@graph-canvas', 'renderer', 'canvas')
        ->assertNoJavaScriptErrors();
});
