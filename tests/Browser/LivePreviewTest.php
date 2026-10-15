<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;
use App\Services\Settings;

function livePreviewNote(): array
{
    $vault = vaultWithFiles([
        'Plan.md' => "# Plan\n\nSome **bold** and `code` with [[Other|a link]] and [site](https://example.com).\n\n- [ ] Ship it\n- Item\n\n> Quoted",
        'Other.md' => '# Other',
    ]);
    resolve(IndexVault::class)->handle($vault);

    return [$vault, Note::query()->where('path', 'Plan.md')->sole()];
}

function lineText(int $index): string
{
    return "document.querySelectorAll('.cm-line')[{$index}].textContent";
}

it('hides markdown syntax on lines that are not being edited', function (): void {
    [$vault, $note] = livePreviewNote();

    visit(route('notes.show', [$vault, $note]))
        ->assertScript(lineText(0), 'Plan')
        ->assertScript(lineText(2), 'Some bold and code with a link and site.')
        ->assertScript(lineText(4), 'Ship it')
        ->assertScript(lineText(5), '• Item')
        ->assertScript(lineText(7), 'Quoted')
        ->assertPresent('@live-checkbox')
        ->assertNoJavaScriptErrors();
});

it('shows the raw markdown on the line being edited', function (): void {
    [$vault, $note] = livePreviewNote();

    visit(route('notes.show', [$vault, $note]))
        ->click('@note-editor')
        ->keys('@note-editor', 'Control+Home')
        ->assertScript(lineText(0), '# Plan')
        ->assertScript(lineText(2), 'Some bold and code with a link and site.')
        ->assertNoJavaScriptErrors();
});

it('ticks tasks from their checkbox', function (): void {
    [$vault, $note] = livePreviewNote();

    visit(route('notes.show', [$vault, $note]))
        ->click('@live-checkbox')
        ->wait(1)
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Plan.md'))->toContain('- [x] Ship it');
});

it('can be switched off in preferences', function (): void {
    [$vault, $note] = livePreviewNote();
    resolve(Settings::class)->update(['live_preview' => false]);

    visit(route('notes.show', [$vault, $note]))
        ->assertScript(lineText(0), '# Plan')
        ->assertMissing('@live-checkbox')
        ->assertNoJavaScriptErrors();
});

it('folds frontmatter into a properties chip until the cursor goes inside', function (): void {
    $vault = vaultWithFiles(['Plan.md' => "---\nstatus: active\ntags:\n  - a\nowner: Sam\n---\n# Plan\n\nBody"]);
    resolve(IndexVault::class)->handle($vault);

    visit(route('notes.show', [$vault, Note::query()->sole()]))
        ->assertSeeIn('@properties-chip', '3 properties')
        ->assertScript(lineText(0), 'Plan')
        ->click('@properties-chip')
        ->assertMissing('@properties-chip')
        ->assertScript(lineText(1), 'status: active')
        ->keys('@note-editor', ['Control+End'])
        ->assertSeeIn('@properties-chip', '3 properties')
        ->assertNoJavaScriptErrors();
});
