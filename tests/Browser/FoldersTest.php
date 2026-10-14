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
