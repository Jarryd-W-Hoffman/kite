<?php

declare(strict_types=1);

use App\Actions\CreateFolder;
use App\Actions\IndexVault;
use App\Models\Note;

it('guides a new vault towards its first note', function (): void {
    $vault = vaultWithFiles();
    resolve(IndexVault::class)->handle($vault);

    $page = visit(route('vaults.show', $vault));

    $page->assertSee("{$vault->name} is empty")
        ->assertVisible('@getting-started')
        ->assertSeeIn('@getting-started', 'Ctrl+K')
        ->assertSeeIn('@tree-empty', 'No notes yet.')
        ->click('Create your first note')
        ->assertVisible('@note-editor')
        ->assertNoJavaScriptErrors();

    expect(Note::query()->where('vault_id', $vault->id)->value('path'))->toBe('Untitled.md');
});

it("opens today's note from an empty vault", function (): void {
    $vault = vaultWithFiles();
    resolve(IndexVault::class)->handle($vault);

    visit(route('vaults.show', $vault))
        ->click("Today's note")
        ->assertVisible('@note-editor')
        ->assertNoJavaScriptErrors();

    expect(Note::query()->where('vault_id', $vault->id)->value('path'))->toStartWith('Daily/');
});

it('explains empty and filtered graphs', function (): void {
    $empty = vaultWithFiles();
    resolve(IndexVault::class)->handle($empty);

    visit(route('vault-graph.show', $empty))
        ->assertSee('No notes to graph yet')
        ->assertNoJavaScriptErrors();

    $vault = vaultWithFiles(['Alone.md' => '# Alone']);
    resolve(IndexVault::class)->handle($vault);

    visit(route('vault-graph.show', $vault))
        ->assertVisible('@graph-no-links')
        ->click('Orphans')
        ->assertSee('Everything is filtered out')
        ->click('@reset-graph-filters')
        ->assertVisible('@graph-canvas')
        ->assertNoJavaScriptErrors();
});

it('marks empty folders in the tree', function (): void {
    $vault = vaultWithFiles(['Note.md' => '# Note']);
    resolve(IndexVault::class)->handle($vault);
    resolve(CreateFolder::class)->handle($vault, 'Projects');

    visit(route('notes.show', [$vault, Note::query()->sole()]))
        ->assertSeeIn('@tree-empty-folder', 'Empty')
        ->assertNoJavaScriptErrors();
});
