<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('floats side panels over the note in a narrow window', function (): void {
    $vault = vaultWithFiles(['Home.md' => "# Home\n\nWelcome."]);
    resolve(IndexVault::class)->handle($vault);

    $page = visit(route('notes.show', [$vault, Note::query()->sole()]));
    $page->resize(960, 600);

    $page->assertPresent('@side-drawer')
        ->assertScript("document.querySelector('[data-test=note-editor]').getBoundingClientRect().width > 500", true)
        ->click('@toggle-comments')
        ->assertVisible('@comment-rail')
        ->assertCount('@side-drawer', 1)
        ->click('[aria-label="Close comments"]')
        ->assertMissing('@comment-rail')
        ->assertNoJavaScriptErrors();
});

it('keeps the panel beside the note in a wide window', function (): void {
    $vault = vaultWithFiles(['Home.md' => '# Home']);
    resolve(IndexVault::class)->handle($vault);

    $page = visit(route('notes.show', [$vault, Note::query()->sole()]));
    $page->resize(1440, 900);

    $page->assertMissing('@side-drawer')->assertNoJavaScriptErrors();
});

it('fits the graph controls in a narrow window', function (): void {
    $vault = vaultWithFiles(['A.md' => '# A [[B]]', 'B.md' => '# B']);
    resolve(IndexVault::class)->handle($vault);

    $page = visit(route('vault-graph.show', $vault));
    $page->resize(960, 600);

    $page->assertScript("[...document.querySelectorAll('header *')].every((element) => element.getBoundingClientRect().right <= window.innerWidth + 1)", true)
        ->assertNoJavaScriptErrors();
});
