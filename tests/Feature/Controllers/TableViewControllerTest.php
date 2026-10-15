<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;
use App\Models\TableView;

beforeEach(function (): void {
    $this->vault = vaultWithFiles([
        'Projects/Alpha.md' => "---\nstatus: active\npriority: 2\n---\n# Alpha",
        'Projects/Beta.md' => "---\nstatus: done\n---\n# Beta",
        'Home.md' => '# Home',
    ]);
    resolve(IndexVault::class)->handle($this->vault);
});

it('creates a view and opens it for editing', function (): void {
    $this->post(route('table-views.store', $this->vault))
        ->assertRedirect(route('table-views.show', ['vault' => $this->vault, 'tableView' => TableView::query()->sole(), 'edit' => 1]));

    $this->get(route('notes.show', [$this->vault, Note::query()->where('path', 'Home.md')->sole()]))
        ->assertInertia(fn ($page) => $page->where('table_views.0.name', 'Untitled view'));
});

it('shows the notes a view matches with the keys they use', function (): void {
    $view = TableView::factory()->for($this->vault)->create(['name' => 'Projects', 'query' => 'folder:Projects', 'columns' => ['status']]);

    $this->get(route('table-views.show', [$this->vault, $view]))
        ->assertInertia(fn ($page) => $page
            ->component('views/show')
            ->where('view.columns', ['status'])
            ->where('rows.0.title', 'Alpha')
            ->where('rows.0.properties', ['status' => 'active', 'priority' => 2])
            ->where('rows.1.title', 'Beta')
            ->count('rows', 2)
            ->where('keys', ['status', 'priority']));
});

it('lists every note for an empty query', function (): void {
    $view = TableView::factory()->for($this->vault)->create();

    $this->get(route('table-views.show', [$this->vault, $view]))
        ->assertInertia(fn ($page) => $page->count('rows', 3));
});

it('saves a view and rejects bad queries', function (): void {
    $view = TableView::factory()->for($this->vault)->create();

    $this->fromRoute('table-views.show', [$this->vault, $view])
        ->put(route('table-views.update', [$this->vault, $view]), ['name' => ' Active ', 'query' => 'status:active', 'columns' => ['status', 'priority'], 'sort_key' => 'priority', 'sort_desc' => true, 'layout' => 'board', 'group_by' => 'status'])
        ->assertRedirectToRoute('table-views.show', [$this->vault, $view]);

    expect($view->refresh()->toSummary())->toBe([
        'id' => $view->id, 'name' => 'Active', 'query' => 'status:active', 'columns' => ['status', 'priority'], 'sort_key' => 'priority', 'sort_desc' => true, 'layout' => 'board', 'group_by' => 'status',
    ]);

    $this->fromRoute('table-views.show', [$this->vault, $view])
        ->put(route('table-views.update', [$this->vault, $view]), ['name' => 'X', 'columns' => [], 'layout' => 'gallery'])
        ->assertSessionHasErrors('layout');

    $this->fromRoute('table-views.show', [$this->vault, $view])
        ->put(route('table-views.update', [$this->vault, $view]), ['name' => 'X', 'query' => 'tag:', 'columns' => []])
        ->assertSessionHasErrors('query');
});

it('deletes a view', function (): void {
    $view = TableView::factory()->for($this->vault)->create();

    $this->delete(route('table-views.destroy', [$this->vault, $view]))->assertRedirectToRoute('vaults.show', $this->vault);

    expect(TableView::query()->count())->toBe(0);
});

it('sets one property in a note file', function (): void {
    $note = Note::query()->where('path', 'Projects/Beta.md')->sole();

    $this->fromRoute('vaults.index')
        ->put(route('note-frontmatter.update', [$this->vault, $note]), ['key' => 'priority', 'value' => 5])
        ->assertRedirectToRoute('vaults.index');

    expect(file_get_contents($this->vault->path.'/Projects/Beta.md'))->toBe("---\nstatus: done\npriority: 5\n---\n# Beta");

    $this->put(route('note-frontmatter.update', [$this->vault, $note]), ['key' => 'bad:key', 'value' => 1])->assertSessionHasErrors('key');
});

it('shows every note when a saved query no longer parses', function (): void {
    $view = TableView::factory()->for($this->vault)->create(['query' => 'tag:']);

    $this->get(route('table-views.show', [$this->vault, $view]))
        ->assertInertia(fn ($page) => $page->count('rows', 3));
});
