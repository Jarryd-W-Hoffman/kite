<?php

declare(strict_types=1);

use App\Actions\BuildGraph;
use App\Actions\FindUnlinkedMentions;
use App\Actions\IndexVault;
use App\Actions\ListTableRows;
use App\Actions\ListTasks;
use App\Models\Note;
use App\Models\TableView;

beforeEach(function (): void {
    $this->vault = vaultWithFiles([
        'Home.md' => "# Home\n\n- [ ] Real task",
        'Templates/Meeting.md' => "# {{title}}\n\nAbout Home and [[Home]].\n\n- [ ] Agenda",
        'Templates_old/Kept.md' => "# Kept\n\n- [ ] Not a template",
    ]);
    resolve(IndexVault::class)->handle($this->vault);
});

it('keeps templates out of tasks, the open count, the graph, views and mentions', function (): void {
    $home = Note::query()->where('path', 'Home.md')->sole();

    expect(array_column(resolve(ListTasks::class)->handle($this->vault), 'text'))->toBe(['Real task', 'Not a template'])
        ->and(array_column(resolve(BuildGraph::class)->handle($this->vault)['nodes'], 'title'))->toBe(['Home', 'Kept'])
        ->and(resolve(BuildGraph::class)->handle($this->vault)['edges'])->toBe([])
        ->and(array_column(resolve(ListTableRows::class)->handle(TableView::factory()->for($this->vault)->create())['rows'], 'title'))->toBe(['Home', 'Kept'])
        ->and(resolve(FindUnlinkedMentions::class)->handle($home))->toBe([]);

    $this->get(route('notes.show', [$this->vault, $home]))->assertInertia(fn ($page) => $page->where('open_tasks', 2));
});

it('includes everything when templates are turned off', function (): void {
    config(['kite.templates.folder' => '']);

    expect(resolve(ListTasks::class)->handle($this->vault))->toHaveCount(3);
});
