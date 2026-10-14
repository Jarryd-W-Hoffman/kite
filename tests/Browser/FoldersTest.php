<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('creates folders and notes inside them from the file tree', function (): void {
    $vault = vaultWithFiles(['Home.md' => '# Home']);
    resolve(IndexVault::class)->handle($vault);

    $page = visit(route('notes.show', [$vault, Note::query()->sole()]));

    $page->click('@new-folder')
        ->assertVisible('@folder-dialog')
        ->fill('folder-name', 'Projects')
        ->click('Create folder')
        ->assertMissing('@folder-dialog')
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
        ->assertMissing('@folder-dialog')
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
        ->assertVisible('@folder-dialog')
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

it('renames folders from their menu and moves them by dragging', function (): void {
    $vault = vaultWithFiles([
        'Home.md' => "# Home\n\n[[Projects/Plan]]",
        'Projects/Plan.md' => '# Plan',
        'Archive/Old.md' => '# Old',
    ]);
    resolve(IndexVault::class)->handle($vault);

    visit(route('notes.show', [$vault, Note::query()->where('path', 'Home.md')->sole()]))
        ->click('[data-test=tree-folder][data-path="Projects"] [data-test=tree-folder-actions]')
        ->click('@tree-rename-folder')
        ->assertValue('folder-name', 'Projects')
        ->fill('folder-name', 'Work')
        ->click('Rename')
        ->assertMissing('@folder-dialog')
        ->assertPresent('a[data-path="Work/Plan.md"]')
        ->drag('[data-test=tree-folder][data-path="Work"] button[draggable]', '[data-test=tree-folder][data-path="Archive"]')
        ->assertPresent('a[data-path="Archive/Work/Plan.md"]')
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Home.md'))->toContain('[[Archive/Work/Plan]]');
});

it('deletes a folder after confirming and leaves it if the open note was inside', function (): void {
    $vault = vaultWithFiles(['Home.md' => '# Home', 'Projects/Plan.md' => '# Plan', 'Projects/Next.md' => '# Next']);
    resolve(IndexVault::class)->handle($vault);

    visit(route('notes.show', [$vault, Note::query()->where('path', 'Projects/Plan.md')->sole()]))
        ->click('@tree-folder-actions')
        ->click('@tree-delete-folder')
        ->assertSee('Delete “Projects”?')
        ->assertSee('The folder and its 2 notes will be permanently deleted.')
        ->click('Delete folder')
        ->assertSee('Deleted “Projects”.')
        ->assertMissing('@tree-folder')
        ->assertNoJavaScriptErrors();

    expect(is_dir($vault->path.'/Projects'))->toBeFalse()
        ->and(Note::query()->pluck('path')->all())->toBe(['Home.md']);
});
