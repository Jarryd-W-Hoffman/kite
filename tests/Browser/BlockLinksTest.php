<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('gives the block at the cursor an id and copies a link to it', function (): void {
    $vault = vaultWithFiles(['Guide.md' => "# Guide\n\nThe key point."]);
    resolve(IndexVault::class)->handle($vault);

    $page = visitSplit(route('notes.show', [$vault, Note::query()->sole()]))
        ->click('.cm-line:last-child')
        ->keys('@note-editor', ['End'])
        ->keys('@note-editor', ['Control+k'])
        ->typeSlowly('@command-palette-input', '>block link')
        ->keys('@command-palette-input', 'Enter')
        ->wait(1.5);

    $page->assertScript("/The key point\\. \\^[a-z0-9]{6}$/.test(document.querySelector('.cm-line:last-child').textContent)", true)
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Guide.md'))->toMatch('/^# Guide\n\nThe key point\. \^[a-z0-9]{6}$/');
});

it('follows a link to a block, and shows which heading or block links point at', function (): void {
    $vault = vaultWithFiles([
        'Home.md' => 'Read [[Guide#^key]] and [[Guide#Usage]].',
        'Guide.md' => "# Guide\n\n## Usage\n\nUse it.\n\nThe key point. ^key",
    ]);
    resolve(IndexVault::class)->handle($vault);

    visitSplit(route('notes.show', [$vault, Note::query()->where('path', 'Home.md')->sole()]))
        ->click('[data-test=note-preview] a[href$="#^key"]')
        ->assertPathEndsWith('/notes/'.Note::query()->where('path', 'Guide.md')->value('id'))
        ->assertPresent('#b-key')
        ->assertSeeIn('#b-key', 'The key point.')
        ->assertDontSeeIn('@note-preview', '^key')
        ->assertSeeIn('[data-test=outline-usage] [data-test=heading-links]', '1')
        ->assertSeeIn('@backlink-target', '→ ^key')
        ->assertNoJavaScriptErrors();
});
