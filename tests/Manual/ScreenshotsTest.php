<?php

declare(strict_types=1);

use App\Actions\DeleteNote;
use App\Actions\IndexVault;
use App\Actions\LockNote;
use App\Actions\SaveNote;
use App\Models\Note;
use App\Models\TableView;
use App\Models\Vault;
use App\Services\NoteLocks;
use Illuminate\Support\Facades\File;
use Tests\Support\DemoVault;

/*
 * The screenshots in docs/manual, taken of a demo vault. Run this after changing what a
 * screenshot shows, and commit the images it writes:
 *
 *     bun run build && php artisan test --testsuite=Manual
 *
 * Each test calls `$page = visit(…)` itself: that's how Pest knows it needs the browser.
 */

beforeEach(function (): void {
    // A neutral path, as the vault picker shows it.
    $path = sys_get_temp_dir().'/Kite demo/My notes';

    File::deleteDirectory($path);
    File::ensureDirectoryExists($path);
    TemporaryVaults::$paths[] = dirname($path);

    $this->vault = Vault::factory()->create(['name' => 'My notes', 'path' => realpath($path)]);

    DemoVault::fill($this->vault);
});

function demoNote(string $path): Note
{
    return Note::query()->where('path', $path)->sole();
}

function noteUrl(Vault $vault, string $path): string
{
    return route('notes.show', [$vault, demoNote($path)]);
}

/**
 * A page at the size of the manual's screenshots, showing notes in the given view.
 */
function framed(mixed $page, string $mode = 'split'): mixed
{
    $page->resize(1440, 900);
    $page->script("localStorage.setItem('kite:view-mode', '{$mode}'); document.querySelector('[aria-label=".ucfirst($mode)."]:not([data-state=on])')?.click()");

    return $page->wait(1.5);
}

/**
 * Save what the page shows now (or just one element of it) as docs/manual/images/{name}.png.
 */
function shoot(mixed $page, string $name, ?string $element = null): void
{
    $element === null ? $page->screenshot(false, $name) : $page->screenshotElement($element, $name);

    File::ensureDirectoryExists(base_path('docs/manual/images'));
    File::copy(base_path("tests/Browser/Screenshots/{$name}.png"), base_path("docs/manual/images/{$name}.png"));

    expect(getimagesize(base_path("docs/manual/images/{$name}.png")))->not->toBeFalse("The {$name} screenshot wasn't written.");
}

it('shows getting started', function (): void {
    $page = visit(route('vaults.index'));
    shoot(framed($page), 'vault-picker');

    $page = visit(noteUrl($this->vault, 'Projects/Kite launch.md'));
    shoot(framed($page), 'workspace');

    $page = visit(route('welcome.show', $this->vault));
    shoot(framed($page), 'welcome');
});

it('shows writing', function (): void {
    $page = visit(noteUrl($this->vault, 'Projects/Kite launch.md'));
    shoot(framed($page, 'edit'), 'live-preview');

    shoot($page, 'properties', '[data-test=properties]');

    $page = visit(noteUrl($this->vault, 'Home.md'));
    framed($page, 'edit')
        ->click('.cm-line:last-child')
        ->keys('@note-editor', ['Control+End', 'Enter', 'Enter'])
        ->typeSlowly('@note-editor', '/')
        ->wait(0.5);
    shoot($page, 'slash-commands');

    $page = visit(noteUrl($this->vault, 'Resources/Graph theory.md'));
    shoot(framed($page), 'rich-markdown');

    $page = visit(noteUrl($this->vault, 'Projects/Roadmap.md'));
    framed($page)->withKeyDown('Control', fn ($page) => $page->withKeyDown('Shift', fn ($page) => $page->click('nav a[href="'.route('notes.show', [$this->vault, demoNote('Projects/Pricing ideas.md')], absolute: false).'"]')))->wait(1.5);
    shoot($page, 'split-pane');

    $page = visit(noteUrl($this->vault, 'Areas/Writing.md'));
    framed($page, 'edit')
        ->keys('@note-editor', 'Control+k')
        ->typeSlowly('@command-palette-input', '>focus mode')
        ->keys('@command-palette-input', 'Enter')
        ->wait(1);
    shoot($page, 'focus-mode');
});

it('shows daily notes, templates and quick capture', function (): void {
    $page = visit(noteUrl($this->vault, 'Daily/2026-10-22.md'));
    shoot(framed($page), 'daily-note');

    $page->keys('@note-editor', ['Control+Shift+n'])->wait(0.5);
    shoot($page, 'template-dialog');

    $page = visit(route('capture.create'));
    $page->resize(560, 300)->typeSlowly('@capture-text', 'Call the venue about the projector')->wait(0.3);
    shoot($page, 'quick-capture');
});

it('shows links', function (): void {
    $page = visit(noteUrl($this->vault, 'Areas/Writing.md'));
    framed($page)
        ->click('.cm-line:last-child')
        ->keys('@note-editor', ['Control+End', 'Enter', 'Enter'])
        ->typeSlowly('@note-editor', 'See [[Ro')
        ->wait(0.5);
    shoot($page, 'link-autocomplete');

    $page = visit(noteUrl($this->vault, 'Home.md'));
    framed($page)->hover('[data-test=note-preview] a.wiki-link[data-wiki-target="Kite launch"]:not([href*="#"])')->wait(1.2);
    shoot($page, 'link-preview');

    $page = visit(noteUrl($this->vault, 'Projects/Kite launch.md'));
    shoot(framed($page), 'backlinks', '[data-test=backlinks]');
});

it('shows tags and colour groups', function (): void {
    $page = visit(route('vault-tags.show', [$this->vault, 'project']));
    shoot(framed($page), 'tag-page');

    $page = visit(route('vault-graph.show', $this->vault));
    framed($page)->wait(2)->click('@edit-colour-groups')->wait(0.8);
    shoot($page, 'colour-groups');
});

it('shows search', function (): void {
    $page = visit(noteUrl($this->vault, 'Home.md'));
    framed($page)->keys('@note-editor', 'Control+k')->typeSlowly('@command-palette-input', 'launch tag:project')->wait(0.8);
    shoot($page, 'search');

    $page->keys('@command-palette-input', ['Control+a', 'Backspace'])->typeSlowly('@command-palette-input', '>')->wait(0.5);
    shoot($page, 'commands');

    $page = visit(route('saved-searches.show', [$this->vault, 1]));
    shoot(framed($page), 'saved-search');
});

it('shows views and tasks', function (): void {
    $page = visit(route('table-views.show', [$this->vault, 1]));
    shoot(framed($page), 'table-view');

    TableView::query()->whereKey(1)->update(['layout' => 'board']);
    $page = visit(route('table-views.show', [$this->vault, 1]));
    shoot(framed($page), 'board-view');

    $page = visit(route('tasks.index', $this->vault));
    shoot(framed($page), 'tasks');
});

it('shows the graph', function (): void {
    $page = visit(route('vault-graph.show', $this->vault));
    shoot(framed($page)->wait(3), 'graph');

    $page = visit(noteUrl($this->vault, 'Projects/Kite launch.md'));
    shoot(framed($page), 'local-graph', '[data-test=local-graph]');
});

it('shows canvases', function (): void {
    $page = visit(route('canvases.show', [$this->vault, 'Launch plan.canvas']));
    framed($page)->click('[aria-label="Show everything"]')->wait(1);
    shoot($page, 'canvas');
});

it('shows flashcards', function (): void {
    $page = visit(route('flashcards.index', $this->vault));
    shoot(framed($page), 'flashcard-decks');

    $page->click('@study-all')->wait(0.5)->click('@show-answer')->wait(0.5);
    shoot($page, 'flashcard-review');
});

it('shows refactoring', function (): void {
    $page = visit(noteUrl($this->vault, 'Inbox/Mobile companion.md'));
    framed($page, 'edit')
        ->click('.cm-line:last-child')
        ->keys('@note-editor', ['End', 'Shift+Home'])
        ->keys('@note-editor', 'Control+k')
        ->typeSlowly('@command-palette-input', '>extract')
        ->keys('@command-palette-input', 'Enter')
        ->wait(0.5)
        ->typeSlowly('[data-test=extract-dialog] input', 'Capture on the go');
    shoot($page, 'extract-dialog');

    $page = visit(noteUrl($this->vault, 'Inbox/Mobile companion.md'));
    framed($page)->click('@note-actions')->click('@merge-note')->wait(0.5);
    shoot($page, 'merge-notes');
});

it('shows history and recovery', function (): void {
    $note = demoNote('Projects/Roadmap.md');
    $save = resolve(SaveNote::class);
    $text = (string) file_get_contents($this->vault->path.'/Projects/Roadmap.md');
    $save->handle($note, str_replace('- Plugins, once the core is stable', "- Plugins, once the core is stable\n- A public API", $text));

    $page = visit(noteUrl($this->vault, 'Projects/Roadmap.md'));
    framed($page)->click('@note-actions')->click('@open-history')->wait(1);
    shoot($page, 'version-history');

    resolve(DeleteNote::class)->handle(demoNote('Inbox/Mobile companion.md'));
    $page = visit(noteUrl($this->vault, 'Home.md'));
    framed($page)->click('My notes')->click('@recently-deleted')->wait(1);
    shoot($page, 'recently-deleted');

    $page = visit(noteUrl($this->vault, 'Areas/Writing.md'));
    framed($page);
    file_put_contents($this->vault->path.'/Areas/Writing.md', "---\ntags: [area/writing]\n---\n# Writing\n\nNotes on writing plainly: short sentences, one idea each. Related: [[Zettelkasten]].\n");
    $page->click('.cm-line:last-child')
        ->keys('@note-editor', ['Control+End', 'Enter', 'Enter'])
        ->typeSlowly('@note-editor', 'Read it aloud before sending.')
        ->wait(1.5);
    shoot($page, 'save-conflict');

    $page->click('@conflict-merge')->wait(0.8);
    shoot($page, 'merge-view');

    file_put_contents($this->vault->path."/Projects/Roadmap (Sam's conflicted copy 2026-10-21).md", "---\nstatus: active\n---\n# Roadmap\n\n## Q4\n\n- Mobile companion\n- Faster sync for big vaults\n- Better search ranking\n");
    resolve(IndexVault::class)->handle($this->vault);
    $page = visit(noteUrl($this->vault, 'Projects/Roadmap.md'));
    shoot(framed($page), 'conflicted-copy');
});

it('shows locked notes', function (): void {
    $key = resolve(NoteLocks::class)->setUp($this->vault, 'correct horse battery staple');
    resolve(LockNote::class)->handle(demoNote('Areas/Writing.md'), $key);

    $page = visit(noteUrl($this->vault, 'Areas/Writing.md'));
    shoot(framed($page), 'locked-note');
});

it('shows comments', function (): void {
    $page = visit(noteUrl($this->vault, 'Projects/Kite launch.md'));
    framed($page, 'edit')->click('@toggle-comments')->wait(0.8);
    shoot($page, 'comments');
});

it('shows exporting, publishing and importing', function (): void {
    $page = visit(noteUrl($this->vault, 'Projects/Kite launch.md'));
    framed($page)->click('@note-actions')->wait(0.4);
    shoot($page, 'note-actions');

    $page = visit(noteUrl($this->vault, 'Home.md'));
    framed($page)->click('My notes')->click('@import-notion')->wait(0.5);
    shoot($page, 'notion-import');
});

it('shows preferences and shortcuts', function (): void {
    $page = visit(noteUrl($this->vault, 'Home.md'));
    framed($page)->click('@preferences-link')->wait(0.8);
    shoot($page, 'preferences');

    $page = visit(noteUrl($this->vault, 'Home.md'));
    framed($page)->click('@shortcuts-link')->wait(0.5);
    shoot($page, 'shortcuts');

    $page = visit(noteUrl($this->vault, 'Projects/Kite launch.md'))->inDarkMode();
    shoot(framed($page), 'dark-mode');
});
