<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('creates folders and notes inside them from the file tree', function (): void {
    $vault = vaultWithFiles(['Home.md' => '# Home']);
    resolve(IndexVault::class)->handle($vault);

    $page = visit(route('notes.show', [$vault, Note::query()->sole()]));

    $page->click('@new-folder')
        ->assertVisible('@new-folder-dialog')
        ->fill('folder-name', 'Projects')
        ->click('Create folder')
        ->assertMissing('@new-folder-dialog')
        ->assertSeeIn('@tree-folder', 'Projects')
        ->click('@tree-folder-actions')
        ->click('@tree-new-note')
        ->assertSee('Projects/Untitled.md')
        ->assertMissing('@tree-new-note')
        ->click('@tree-folder-actions')
        ->click('@tree-new-folder')
        ->assertSee('Create a folder inside Projects.')
        ->fill('folder-name', '2026')
        ->click('Create folder')
        ->assertMissing('@new-folder-dialog')
        ->assertSee('2026')
        ->assertNoJavaScriptErrors();

    expect(Note::query()->where('path', 'Projects/Untitled.md')->exists())->toBeTrue()
        ->and(is_dir($vault->path.'/Projects/2026'))->toBeTrue();
});

it('explains why a folder could not be created', function (): void {
    $vault = vaultWithFiles(['Taken/Note.md' => '# Note']);
    resolve(IndexVault::class)->handle($vault);

    visit(route('notes.show', [$vault, Note::query()->sole()]))
        ->click('@new-folder')
        ->fill('folder-name', 'Taken')
        ->click('Create folder')
        ->assertSee('A folder or file with that name already exists.')
        ->assertVisible('@new-folder-dialog')
        ->assertNoJavaScriptErrors();
});

it('moves notes between folders by dragging them in the file tree', function (): void {
    $vault = vaultWithFiles([
        'Home.md' => "# Home\n\nSee [[Inbox]].",
        'Inbox.md' => '# Inbox',
        'Projects/Plan.md' => '# Plan',
    ]);
    resolve(IndexVault::class)->handle($vault);

    $page = visit(route('notes.show', [$vault, Note::query()->where('path', 'Home.md')->sole()]));

    $page->drag('a[data-path="Inbox.md"]', '[data-test=tree-folder][data-path="Projects"]')
        ->assertPresent('a[data-path="Projects/Inbox.md"]')
        ->assertSee('Home')
        ->drag('a[data-path="Projects/Plan.md"]', 'a[data-path="Home.md"]')
        ->assertPresent('a[data-path="Plan.md"]')
        ->assertNoJavaScriptErrors();

    expect(Note::query()->pluck('path')->sort()->values()->all())->toBe(['Home.md', 'Plan.md', 'Projects/Inbox.md'])
        ->and(file_exists($vault->path.'/Projects/Inbox.md'))->toBeTrue()
        ->and(file_get_contents($vault->path.'/Home.md'))->toContain('[[Inbox]]');
});

it('drops a wiki link when a note is dragged from the tree into the editor', function (): void {
    $vault = vaultWithFiles(['Home.md' => "# Home\n\n", 'Projects/Plan.md' => '# Plan']);
    resolve(IndexVault::class)->handle($vault);

    // Dispatch the drag events directly: the tree fills the DataTransfer, the editor consumes it.
    $dropFromTree = <<<'JS'
        (() => {
            const data = new DataTransfer();
            const source = document.querySelector('a[data-path="Projects/Plan.md"]');
            source.dispatchEvent(new DragEvent('dragstart', { bubbles: true, dataTransfer: data }));

            const target = document.querySelector('[data-test=note-editor] .cm-line:last-child');
            const box = target.getBoundingClientRect();
            target.dispatchEvent(new DragEvent('drop', { bubbles: true, cancelable: true, dataTransfer: data, clientX: box.left + 1, clientY: box.top + box.height / 2 }));
            source.dispatchEvent(new DragEvent('dragend', { bubbles: true, dataTransfer: data }));

            return data.getData('text/plain');
        })()
        JS;

    visit(route('notes.show', [$vault, Note::query()->where('path', 'Home.md')->sole()]))
        ->assertScript($dropFromTree, '[[Plan]]')
        ->assertSeeIn('@note-editor', '[[Plan]]')
        ->assertNoJavaScriptErrors();

    expect(Note::query()->where('path', 'Projects/Plan.md')->exists())->toBeTrue();
});
