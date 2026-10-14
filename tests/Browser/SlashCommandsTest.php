<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

function slashNote(array $files = []): array
{
    $vault = vaultWithFiles(['Plan.md' => '', ...$files]);
    resolve(IndexVault::class)->handle($vault);

    return [$vault, Note::query()->where('path', 'Plan.md')->sole()];
}

it('inserts blocks from the slash menu', function (): void {
    [$vault, $note] = slashNote();

    visit(route('notes.show', [$vault, $note]))
        ->click('@note-editor')
        ->typeSlowly('@note-editor', '/to')
        ->assertSee('To-do')
        ->assertSee('A task with a checkbox')
        ->keys('@note-editor', 'Enter')
        ->typeSlowly('@note-editor', 'Buy milk')
        ->keys('@note-editor', 'Enter')
        ->typeSlowly('@note-editor', '/h2')
        ->keys('@note-editor', 'Enter')
        ->typeSlowly('@note-editor', 'Later')
        ->wait(1)
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Plan.md'))->toBe("- [ ] Buy milk\n## Later");
});

it('starts block commands on their own line', function (): void {
    [$vault, $note] = slashNote();

    visit(route('notes.show', [$vault, $note]))
        ->click('@note-editor')
        ->typeSlowly('@note-editor', 'Intro /divi')
        ->keys('@note-editor', 'Enter')
        ->wait(1)
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Plan.md'))->toBe("Intro \n---\n");
});

it('inserts a template with its placeholders filled in', function (): void {
    [$vault, $note] = slashNote(['Templates/Meeting.md' => "## Attendees\n\nFor {{title}}"]);

    visit(route('notes.show', [$vault, $note]))
        ->click('@note-editor')
        ->typeSlowly('@note-editor', '/meet')
        ->assertSee('Insert this template')
        ->keys('@note-editor', 'Enter')
        ->wait(1.5)
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Plan.md'))->toBe("## Attendees\n\nFor Plan");
});
