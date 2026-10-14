<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

it('jumps to headings from the outline and heading links', function (): void {
    $vault = vaultWithFiles([
        'Guide.md' => "# Guide\n\nIntro\n\n## Second part\n\nBody",
        'Index.md' => "# Index\n\nRead [[Guide#Second part]].",
    ]);
    resolve(IndexVault::class)->handle($vault);

    $page = visitSplit(route('notes.show', [$vault, Note::query()->where('path', 'Guide.md')->sole()]));

    $page->assertSee('Outline')
        ->click('@outline-second-part')
        ->assertScript('window.location.hash', '#second-part')
        ->assertScript("document.querySelector('.cm-editor').dataset.cursor", '16')
        ->assertNoJavaScriptErrors();

    $page = visitSplit(route('notes.show', [$vault, Note::query()->where('path', 'Index.md')->sole()]));

    $page->click('Guide › Second part')
        ->assertScript('window.location.hash', '#second-part')
        ->assertPresent('#h-second-part')
        ->assertNoJavaScriptErrors();
});

it('highlights the heading the cursor is under', function (): void {
    $vault = vaultWithFiles(['Guide.md' => "# Guide\n\nIntro\n\n## Second part\n\nBody"]);
    resolve(IndexVault::class)->handle($vault);

    visitSplit(route('notes.show', [$vault, Note::query()->sole()]))
        ->click('@outline-second-part')
        ->assertAttribute('@outline-second-part', 'aria-current', 'location')
        ->keys('@note-editor', ['Control+Home'])
        ->assertAttribute('@outline-guide', 'aria-current', 'location')
        ->assertNoJavaScriptErrors();
});

it('groups backlinks by note and opens the linking line', function (): void {
    $vault = vaultWithFiles([
        'Topic.md' => '# Topic',
        'Daily.md' => "# Daily\n\nMorning: [[Topic]]\n\nEvening: back to [[Topic]] again",
        'Other.md' => "# Other\n\nSee [[Topic]]",
    ]);
    resolve(IndexVault::class)->handle($vault);

    visitSplit(route('notes.show', [$vault, Note::query()->where('path', 'Topic.md')->sole()]))
        ->assertCount('@backlink-group', 2)
        ->assertCount('@backlink-line', 3)
        ->assertSeeIn('@panel-section-backlinks', '2')
        ->click('[data-test=backlink-line][title="Line 5"]')
        ->assertPathEndsWith('/notes/'.Note::query()->where('path', 'Daily.md')->value('id'))
        ->assertScript('window.location.hash', '#L5')
        ->assertScript("document.querySelector('.cm-editor').dataset.cursor", (string) mb_strlen("# Daily\n\nMorning: [[Topic]]\n\n"))
        ->assertNoJavaScriptErrors();
});

it('folds panel sections and remembers them', function (): void {
    $vault = vaultWithFiles(['A.md' => "# A\n\n[[B]]", 'B.md' => '# B']);
    resolve(IndexVault::class)->handle($vault);

    visitSplit(route('notes.show', [$vault, Note::query()->where('path', 'A.md')->sole()]))
        ->click('@panel-section-links')
        ->assertAttribute('@panel-section-links', 'aria-expanded', 'false')
        ->refresh()
        ->assertAttribute('@panel-section-links', 'aria-expanded', 'false')
        ->assertNoJavaScriptErrors();
});
