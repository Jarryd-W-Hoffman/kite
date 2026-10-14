<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('lists tasks by when they are due and ticks them off', function (): void {
    $yesterday = now()->subDay()->toDateString();
    $today = now()->toDateString();
    $vault = vaultWithFiles([
        'Plan.md' => "# Plan\n\n- [ ] Late one due:{$yesterday}\n- [ ] Today one due:{$today}\n- [ ] Someday\n- [x] Finished",
    ]);
    resolve(IndexVault::class)->handle($vault);

    visit(route('notes.show', [$vault, Note::query()->sole()]))
        ->assertSeeIn('@tasks-link', '3')
        ->click('@tasks-link')
        ->assertSeeIn('@task-group-overdue', 'Late one')
        ->assertSeeIn('@task-group-today', 'Today one')
        ->assertSeeIn('@task-group-no-date', 'Someday')
        ->assertDontSee('Finished')
        ->click('[data-test=task-group-no-date] [data-test=task-checkbox]')
        ->assertDontSee('Someday')
        ->assertSeeIn('@tasks-link', '2')
        ->click('@tasks-done')
        ->assertSee('Someday')
        ->assertSee('Finished')
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Plan.md'))->toContain('- [x] Someday');
});
