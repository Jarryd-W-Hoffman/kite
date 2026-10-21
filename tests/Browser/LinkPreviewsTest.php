<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('previews the note a link goes to when you hover it', function (): void {
    $vault = vaultWithFiles([
        'Home.md' => "Read [[Guide]] and [[Guide#Usage]]. [[Missing]] isn't there.",
        'Guide.md' => "# Guide\n\nAll about the guide.\n\n## Usage\n\nUse it daily.",
    ]);
    resolve(IndexVault::class)->handle($vault);

    visitSplit(route('notes.show', [$vault, Note::query()->where('path', 'Home.md')->sole()]))
        ->hover('[data-test=note-preview] a.wiki-link[data-wiki-target="Guide"]:not([href*="#"])')
        ->assertSeeIn('@link-peek', 'Guide.md')
        ->assertSeeIn('@link-peek-body', 'All about the guide.')
        ->hover('[data-test=note-preview] a[href$="#usage"]')
        ->wait(0.6)
        ->assertSeeIn('@link-peek', 'Guide › Usage')
        ->assertSeeIn('@link-peek-body', 'Use it daily.')
        ->assertDontSeeIn('@link-peek-body', 'All about the guide.')
        ->keys('@note-editor', 'Escape')
        ->assertMissing('@link-peek')
        ->hover('[data-test=note-preview] a.is-unresolved')
        ->wait(0.6)
        ->assertMissing('@link-peek')
        ->assertNoJavaScriptErrors();
});

it('previews a link in the editor while Ctrl is held', function (): void {
    $vault = vaultWithFiles([
        'Home.md' => 'See [[Guide#Usage]] here.',
        'Guide.md' => "# Guide\n\n## Usage\n\nUse it daily.",
    ]);
    resolve(IndexVault::class)->handle($vault);

    $hover = fn (bool $ctrl): string => "document.querySelector('.cm-wikilink').dispatchEvent(new MouseEvent('mouseover', { bubbles: true, ctrlKey: ".($ctrl ? 'true' : 'false').' }))';

    $page = visitSplit(route('notes.show', [$vault, Note::query()->where('path', 'Home.md')->sole()]));

    $page->script($hover(false));
    $page->wait(0.6)->assertMissing('@link-peek');

    $page->script($hover(true));
    $page->assertSeeIn('@link-peek', 'Guide › Usage')
        ->assertSeeIn('@link-peek-body', 'Use it daily.')
        ->assertNoJavaScriptErrors();
});
