<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;
use App\Models\TableView;

it('builds a table of notes and edits their properties inline', function (): void {
    $vault = vaultWithFiles([
        'Projects/Alpha.md' => "---\nstatus: active\npriority: 2\ndone: false\n---\n# Alpha",
        'Projects/Beta.md' => "---\nstatus: paused\npriority: 1\ndone: true\n---\n# Beta",
        'Home.md' => '# Home',
    ]);
    resolve(IndexVault::class)->handle($vault);

    $page = visitSplit(route('notes.show', [$vault, Note::query()->where('path', 'Home.md')->sole()]))
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

it('shows a view as a board and moves cards between columns', function (): void {
    $vault = vaultWithFiles([
        'Projects/Alpha.md' => "---\nstatus: active\npriority: 2\n---\n# Alpha",
        'Projects/Beta.md' => "---\nstatus: done\n---\n# Beta",
        'Projects/Gamma.md' => '# Gamma',
    ]);
    resolve(IndexVault::class)->handle($vault);
    $view = TableView::factory()->for($vault)->create(['name' => 'Projects', 'query' => 'folder:Projects', 'columns' => ['priority']]);

    visit(route('table-views.show', [$vault, $view]))
        ->click('@layout-board')
        ->assertVisible('@view-dialog')
        ->select('group_by', 'status')
        ->click('Save view')
        ->assertVisible('@view-board')
        ->assertCount('@board-column', 3)
        ->assertSeeIn('[data-test=board-column][data-value=active]', 'Alpha')
        ->assertSeeIn('[data-test=board-column][data-value=""]', 'Gamma')
        ->assertSeeIn('[data-test=board-card][data-title=Alpha]', 'priority')
        ->drag('[data-test=board-card][data-title=Alpha]', '[data-test=board-column][data-value=done]')
        ->assertSeeIn('[data-test=board-column][data-value=done]', 'Alpha')
        ->drag('[data-test=board-card][data-title=Beta]', '[data-test=board-column][data-value=""]')
        ->assertSeeIn('[data-test=board-column][data-value=""]', 'Beta')
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Projects/Alpha.md'))->toContain('status: done')
        ->and(file_get_contents($vault->path.'/Projects/Beta.md'))->toContain("status:\n");
});
