<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('creates a note from a template with its placeholders filled in', function (): void {
    $vault = vaultWithFiles([
        'Home.md' => '# Home',
        'Projects/Plan.md' => '# Plan',
        'Templates/Meeting.md' => "# {{title}}\n\nHeld in {{folder}} on {{date}}.",
        'Templates/Person.md' => "# {{title}}\n\nRole:",
    ]);
    resolve(IndexVault::class)->handle($vault);

    visitSplit(route('notes.show', [$vault, Note::query()->where('path', 'Home.md')->sole()]))
        ->click('@tree-folder-actions')
        ->click('@tree-new-from-template')
        ->assertSeeIn('@template-dialog', 'The note is created in Projects.')
        ->assertCount('@template-option', 2)
        ->fill('template-title', 'Kickoff')
        ->click('Create note')
        ->assertVisible('@note-editor')
        ->assertSee('Held in Projects on '.now()->toDateString().'.')
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Projects/Kickoff.md'))->toBe("# Kickoff\n\nHeld in Projects on ".now()->toDateString().'.');
});

it('opens from the keyboard and picks another template', function (): void {
    $vault = vaultWithFiles([
        'Home.md' => '# Home',
        'Templates/Meeting.md' => '# {{title}}',
        'Templates/Person.md' => "# {{title}}\n\nRole:",
    ]);
    resolve(IndexVault::class)->handle($vault);

    visitSplit(route('notes.show', [$vault, Note::query()->where('path', 'Home.md')->sole()]))
        ->keys('@note-editor', 'Control+Shift+N')
        ->assertVisible('@template-dialog')
        ->click('[data-test=template-option]:nth-child(2)')
        ->fill('template-title', 'Ada')
        ->click('Create note')
        ->assertMissing('@template-dialog')
        ->assertSee('Role:')
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Ada.md'))->toBe("# Ada\n\nRole:");
});

it('offers to start a template when there are none', function (): void {
    $vault = vaultWithFiles(['Home.md' => '# Home']);
    resolve(IndexVault::class)->handle($vault);

    visitSplit(route('notes.show', [$vault, Note::query()->sole()]))
        ->click('@new-from-template')
        ->assertSeeIn('@template-dialog', 'No templates yet')
        ->click('@start-template')
        ->assertSee('{{title}}')
        ->assertNoJavaScriptErrors();

    expect(file_exists($vault->path.'/Templates/New template.md'))->toBeTrue();
});
