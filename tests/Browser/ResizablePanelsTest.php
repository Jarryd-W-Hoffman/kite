<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

function panelWidth(string $label): string
{
    return "document.querySelector('aside[aria-label=\"{$label}\"]').getBoundingClientRect().width";
}

it('resizes the sidebar and note details between their narrowest and widest, and remembers it', function (): void {
    $vault = vaultWithFiles(['Home.md' => "# Home\n\nWelcome."]);
    resolve(IndexVault::class)->handle($vault);

    $page = visitSplit(route('notes.show', [$vault, Note::query()->sole()]));
    $page->resize(1600, 900);

    $page->assertScript(panelWidth('Sidebar'), 256)
        ->assertScript(panelWidth('Note details'), 288)
        // Dragging far past either panel's widest stops there.
        ->drag('[aria-label="Resize sidebar"]', '@note-editor')
        ->assertScript(panelWidth('Sidebar'), 448)
        ->drag('[aria-label="Resize note details"]', '@note-editor')
        ->assertScript(panelWidth('Note details'), 480)
        ->keys('[aria-label="Resize sidebar"]', 'Home')
        ->assertScript(panelWidth('Sidebar'), 256)
        ->keys('[aria-label="Resize sidebar"]', 'ArrowRight')
        ->assertScript(panelWidth('Sidebar'), 272)
        // Its handle is on its left edge, so the left arrow widens it, here past its widest.
        ->keys('[aria-label="Resize note details"]', 'ArrowLeft')
        ->assertScript(panelWidth('Note details'), 480)
        ->keys('[aria-label="Resize note details"]', 'ArrowRight')
        ->assertScript(panelWidth('Note details'), 464)
        ->assertNoJavaScriptErrors();

    $page->refresh()
        ->assertScript(panelWidth('Sidebar'), 272)
        ->assertScript(panelWidth('Note details'), 464);

    $page->script("document.querySelector('[aria-label=\"Resize note details\"]').dispatchEvent(new MouseEvent('dblclick', { bubbles: true }))");
    $page->assertScript(panelWidth('Note details'), 288);
});
