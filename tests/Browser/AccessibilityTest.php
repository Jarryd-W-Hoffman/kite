<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;
use App\Models\TableView;
use App\Services\Canvases;
use Illuminate\Support\Facades\Vite;
use Pest\Browser\Api\ArrayablePendingAwaitablePage;
use Pest\Browser\Api\PendingAwaitablePage;

beforeEach(function (): void {
    // Audit the built assets, as the app ships them, even while a Vite dev server is running.
    Vite::useHotFile(storage_path('framework/testing/no-hot'));

    $this->vault = vaultWithFiles([
        'Home.md' => "# Home\n\nSee [[Ideas]]. #project\n\n- [ ] A task #project\n- [x] Done\n\n> [!note] Callout\n> body",
        'Ideas.md' => "---\nstatus: active\n---\n# Ideas\n\nBack to [[Home]]",
        'Folder/Inner.md' => '# Inner',
        'Folder/Deeper/Deep.md' => '# Deep',
    ]);
    resolve(IndexVault::class)->handle($this->vault);
    $this->home = Note::query()->where('path', 'Home.md')->sole();
});

it('has no accessibility issues on any page, in light or dark', function (bool $dark): void {
    $view = TableView::factory()->for($this->vault)->create(['name' => 'All', 'columns' => ['status']]);
    resolve(Canvases::class)->write($this->vault, 'Board.canvas', [['id' => 'a', 'type' => 'text', 'text' => 'Hi', 'x' => 0, 'y' => 0, 'width' => 200, 'height' => 80]], []);
    $visit = fn (string $url): PendingAwaitablePage|ArrayablePendingAwaitablePage => $dark ? visit($url)->inDarkMode() : visit($url);

    $visit(route('notes.show', [$this->vault, $this->home]))->assertSee('Home')->assertNoAccessibilityIssues(3)
        ->click('[aria-label=Preview]')->assertSee('Callout')->assertNoAccessibilityIssues(3)
        ->keys('body', 'Control+k')->assertVisible('@command-palette-input')->wait(0.6)->assertNoAccessibilityIssues(3);
    $visit(route('notes.show', [$this->vault, $this->home]))->click('@preferences-link')->assertVisible('@preferences-dialog')->wait(0.6)->assertNoAccessibilityIssues(3);
    $visit(route('vault-graph.show', $this->vault))->assertSee('Graph')->assertNoAccessibilityIssues(3);
    $visit(route('tasks.index', $this->vault))->assertSee('A task')->assertNoAccessibilityIssues(3);
    $visit(route('table-views.show', [$this->vault, $view]))->assertSee('All')->assertNoAccessibilityIssues(3);
    $visit(route('vault-tags.show', [$this->vault, 'project']))->assertSee('project')->assertNoAccessibilityIssues(3);
    $visit(route('canvases.show', [$this->vault, 'Board.canvas']))->assertSee('Board')->assertNoAccessibilityIssues(3);
    $visit(route('vaults.index'))->assertNoAccessibilityIssues(3);
})->with(['light' => [false], 'dark' => [true]]);

it('moves through the file tree with the arrow keys', function (): void {
    visit(route('notes.show', [$this->vault, $this->home]))
        ->click('[data-tree-item][data-folder="Folder"]')
        ->assertAttribute('[data-folder="Folder"]', 'aria-expanded', 'false')
        ->keys('[data-folder="Folder"]', 'ArrowRight')
        ->assertAttribute('[data-folder="Folder"]', 'aria-expanded', 'true')
        ->keys('[data-folder="Folder"]', 'ArrowRight')
        ->assertScript('document.activeElement.dataset.folder', 'Folder/Deeper')
        ->keys('[data-folder="Folder/Deeper"]', 'ArrowDown')
        ->assertScript('document.activeElement.dataset.path', 'Folder/Deeper/Deep.md')
        ->keys('[data-path="Folder/Deeper/Deep.md"]', 'ArrowLeft')
        ->assertScript('document.activeElement.dataset.folder', 'Folder/Deeper')
        ->keys('[data-folder="Folder/Deeper"]', 'ArrowLeft')
        ->assertAttribute('[data-folder="Folder/Deeper"]', 'aria-expanded', 'false')
        ->keys('[data-folder="Folder/Deeper"]', 'ArrowLeft')
        ->assertScript('document.activeElement.dataset.folder', 'Folder')
        ->keys('[data-folder="Folder"]', 'ArrowLeft')
        ->assertAttribute('[data-folder="Folder"]', 'aria-expanded', 'false')
        ->keys('[data-folder="Folder"]', 'End')
        ->assertScript('document.activeElement.dataset.path', 'Ideas.md')
        ->keys('[data-path="Ideas.md"]', 'Home')
        ->assertScript('document.activeElement.dataset.folder', 'Folder')
        ->assertNoJavaScriptErrors();
});

it('lets keyboard users skip past the sidebar to the note', function (): void {
    visit(route('notes.show', [$this->vault, $this->home]))
        ->keys('body', 'Tab')
        ->assertScript('document.activeElement.textContent', 'Skip to note')
        ->keys('a[href="#note"]', 'Enter')
        ->assertScript('document.activeElement.id', 'note')
        ->assertScript('document.querySelector("h1#note-title").textContent', 'Home');
});
