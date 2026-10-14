<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

beforeEach(function (): void {
    $this->vault = vaultWithFiles(['Templates/Meeting.md' => "# {{title}}\n\nIn {{folder}} on {{date}}.", 'Home.md' => '# Home']);
    resolve(IndexVault::class)->handle($this->vault);
});

it('shares the templates with the workspace', function (): void {
    $this->get(route('notes.show', [$this->vault, Note::query()->where('path', 'Home.md')->sole()]))
        ->assertInertia(fn ($page) => $page->where('templates.0.name', 'Meeting')->where('templates.0.path', 'Templates/Meeting.md'));
});

it('creates a note from a template', function (): void {
    $this->travelTo(now()->setDate(2026, 5, 6));

    $this->post(route('notes.store', $this->vault), ['title' => 'Standup', 'folder' => 'Work', 'template' => 'Templates/Meeting.md'])
        ->assertRedirect();

    expect(file_get_contents($this->vault->path.'/Work/Standup.md'))->toBe("# Standup\n\nIn Work on 2026-05-06.");
});

it('rejects missing or outside templates', function (string $template): void {
    $this->fromRoute('vaults.show', $this->vault)
        ->post(route('notes.store', $this->vault), ['title' => 'X', 'template' => $template])
        ->assertSessionHasErrors(['template' => 'That template could not be found.']);
})->with(['missing' => 'Templates/Nope.md', 'outside' => '../secret.md']);

it('starts a template with example placeholders', function (): void {
    $this->post(route('templates.store', $this->vault))->assertRedirect();

    expect(file_get_contents($this->vault->path.'/Templates/New template.md'))->toContain('# {{title}}')->toContain('{{date:l, j F Y}}');
});

it('renders a template for inserting into a note', function (): void {
    $this->travelTo(now()->setDate(2026, 5, 6));

    $this->getJson(route('templates.show', ['vault' => $this->vault, 'path' => 'Templates/Meeting.md', 'title' => 'Standup', 'folder' => 'Work']))
        ->assertOk()
        ->assertExactJson(['contents' => "# Standup\n\nIn Work on 2026-05-06."]);
});

it('only renders notes in the templates folder', function (string $path): void {
    $this->getJson(route('templates.show', ['vault' => $this->vault, 'path' => $path]))->assertNotFound();
})->with(['outside the folder' => 'Home.md', 'missing' => 'Templates/Nope.md', 'escaping' => '../secret.md']);
