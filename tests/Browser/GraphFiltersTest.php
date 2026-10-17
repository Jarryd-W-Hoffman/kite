<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

beforeEach(function (): void {
    $this->vault = vaultWithFiles([
        'Work/A.md' => "# A\n\n[[B]]",
        'Work/B.md' => "# B\n\n[[C]]",
        'C.md' => "# C\n\n[[D]]",
        'D.md' => '# D',
    ]);
    resolve(IndexVault::class)->handle($this->vault);
});

it('filters the graph with the search filter language', function (): void {
    visit(route('vault-graph.show', $this->vault))
        ->assertSee('4 notes · 3 links')
        ->type('@graph-filter', 'folder:Work')
        ->assertSee('2 notes · 1 links')
        ->assertQueryStringHas('filter', 'folder:Work')
        ->clear('@graph-filter')
        ->type('@graph-filter', 'tag:')
        ->assertSeeIn('@graph-filter-error', 'needs a value')
        ->assertSee('4 notes · 3 links')
        ->clear('@graph-filter')
        ->type('@graph-filter', 'folder:Nowhere')
        ->assertSee('No notes match this filter')
        ->click('Clear filter')
        ->assertSee('4 notes · 3 links')
        ->assertNoJavaScriptErrors();
});

it('shows notes further away in the local graph, and remembers how far', function (): void {
    $page = visit(route('notes.show', [$this->vault, Note::query()->where('title', 'A')->sole()]))
        ->assertAttribute('@local-graph', 'data-nodes', '2')
        ->click('@local-graph-depth-3')
        ->assertAttribute('@local-graph', 'data-nodes', '4')
        ->assertAttribute('@local-graph-depth-3', 'aria-pressed', 'true')
        ->click('@local-graph-depth-2')
        ->assertAttribute('@local-graph', 'data-nodes', '3');

    $page->navigate(route('notes.show', [$this->vault, Note::query()->where('title', 'D')->sole()]))
        ->assertAttribute('@local-graph-depth-2', 'aria-pressed', 'true')
        ->assertAttribute('@local-graph', 'data-nodes', '3')
        // Pest's error check crashes, rather than retrying, if its collector isn't on the new page yet.
        ->assertScript('typeof window.__pestBrowser?.jsErrors', 'object')
        ->assertNoJavaScriptErrors();
});
