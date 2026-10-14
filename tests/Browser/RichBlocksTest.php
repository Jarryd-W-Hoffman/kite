<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('renders callouts, math and diagrams in the preview', function (): void {
    $vault = vaultWithFiles(['Rich.md' => <<<'MD'
        # Rich

        > [!warning] Careful
        > Mind the gap.

        > [!tip]- Hidden tip
        > Peekaboo

        Inline $a^2 + b^2$ maths.

        $$
        \int_0^1 x\,dx
        $$

        ```mermaid
        flowchart LR
          Start --> Finish
        ```
        MD]);
    resolve(IndexVault::class)->handle($vault);

    visit(route('notes.show', [$vault, Note::query()->sole()]))
        ->click('[aria-label=Preview]')
        ->assertSeeIn('.callout[data-callout=warning] .callout-title', 'Careful')
        ->assertSee('Mind the gap.')
        ->assertDontSee('Peekaboo')
        ->click('Hidden tip')
        ->assertSee('Peekaboo')
        ->assertPresent('.math-inline .katex')
        ->assertPresent('.math-block .katex-display')
        ->assertPresent('[data-test=mermaid-diagram] svg')
        ->assertSeeIn('[data-test=mermaid-diagram]', 'Finish')
        ->assertNoJavaScriptErrors();
});

it('tints callouts in the editor', function (): void {
    $vault = vaultWithFiles(['Rich.md' => "> [!tip] Nice\n> Body\n\n> Plain"]);
    resolve(IndexVault::class)->handle($vault);

    visit(route('notes.show', [$vault, Note::query()->sole()]))
        ->assertCount('.cm-line.cm-kite-callout-green', 2)
        ->assertCount('.cm-line.cm-kite-quote', 1)
        ->assertNoJavaScriptErrors();
});

it('shows only the callout title in live preview', function (): void {
    $vault = vaultWithFiles(['Rich.md' => "> [!tip] Nice\n> Body"]);
    resolve(IndexVault::class)->handle($vault);

    visit(route('notes.show', [$vault, Note::query()->sole()]))
        ->assertScript("document.querySelectorAll('.cm-line')[0].textContent", 'Nice')
        ->assertNoJavaScriptErrors();
});
