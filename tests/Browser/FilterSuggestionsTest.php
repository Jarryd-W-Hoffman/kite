<?php

declare(strict_types=1);

use App\Actions\IndexVault;

beforeEach(function (): void {
    $this->vault = vaultWithFiles([
        'Projects/shell-tools/Tool.md' => '# Tool',
        'Projects/Website/Home.md' => '# Home',
        'Work/Client A/Brief.md' => '# Brief',
        'Notes.md' => '# Notes',
    ]);
    resolve(IndexVault::class)->handle($this->vault);
});

it('suggests folders by any part of their path and fills in the whole path', function (): void {
    visit(route('vault-graph.show', $this->vault))
        ->assertSee('4 notes · 0 links')
        ->type('@graph-filter', 'folder:shell')
        ->assertSeeIn('@query-suggestions', 'Projects/shell-tools')
        ->keys('@graph-filter', 'Enter')
        ->assertValue('@graph-filter', 'folder:Projects/shell-tools')
        ->assertMissing('@query-suggestions')
        ->assertSee('1 notes · 0 links')
        ->clear('@graph-filter')
        ->type('@graph-filter', 'folder:cli')
        ->click('[data-test=query-suggestions] [role=option]')
        ->assertValue('@graph-filter', 'folder:"Work/Client A"')
        ->assertSee('1 notes · 0 links')
        ->assertNoJavaScriptErrors();
});

it('moves through suggestions with the keyboard, and Escape hides them without closing the editor', function (): void {
    $query = '[data-test=new-colour-group] input[aria-label="Query"]';

    visit(route('vault-graph.show', $this->vault))
        ->select('@graph-colour', 'group')
        ->click('@edit-colour-groups')
        ->assertVisible('@colour-group-editor')
        ->type($query, 'folder:proj')
        ->assertSeeIn('@query-suggestions', 'Projects/Website')
        ->keys($query, 'ArrowDown')
        ->keys($query, 'Enter')
        ->assertValue($query, 'folder:Projects/shell-tools')
        ->assertSeeIn('[data-test=new-colour-group] [data-test=colour-group-count]', '1 note')
        ->clear($query)
        ->type($query, 'folder:')
        ->assertVisible('@query-suggestions')
        ->keys($query, 'Escape')
        ->assertMissing('@query-suggestions')
        ->assertVisible('@colour-group-editor')
        ->assertNoJavaScriptErrors();
});
