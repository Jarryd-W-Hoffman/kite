<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('builds a table of notes and edits their properties inline', function (): void {
    $vault = vaultWithFiles([
        'Projects/Alpha.md' => "---\nstatus: active\npriority: 2\ndone: false\n---\n# Alpha",
        'Projects/Beta.md' => "---\nstatus: paused\npriority: 1\ndone: true\n---\n# Beta",
        'Home.md' => '# Home',
    ]);
    resolve(IndexVault::class)->handle($vault);

    $page = visit(route('notes.show', [$vault, Note::query()->where('path', 'Home.md')->sole()]))
        ->click('@new-view')
        ->assertVisible('@view-dialog')
        ->fill('view-name', 'Projects')
        ->fill('view-query', 'folder:Projects')
        ->assertSeeIn('@view-count', '2 notes match.')
        ->click('[aria-label="Show status"]')
        ->click('[aria-label="Show priority"]')
        ->click('[aria-label="Show done"]')
        ->click('Save view')
        ->assertMissing('@view-dialog')
        ->assertSeeIn('@view-link', 'Projects')
        ->assertCount('@view-row', 2)
        ->click('@sort-priority')
        ->assertSeeIn('[data-test=view-row]:first-child', 'Beta')
        ->fill('[aria-label="Alpha status"]', 'shipped')
        ->keys('[aria-label="Alpha status"]', 'Enter')
        ->wait(1)
        ->click('[aria-label="Alpha done"]')
        ->wait(1)
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Projects/Alpha.md'))->toBe("---\nstatus: shipped\npriority: 2\ndone: true\n---\n# Alpha");
});
