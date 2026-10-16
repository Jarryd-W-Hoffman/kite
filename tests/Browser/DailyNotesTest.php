<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;
use Carbon\CarbonImmutable;

it("opens today's note and moves between days", function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 09:30:00'));

    $vault = vaultWithFiles(['Home.md' => '# Home']);
    resolve(IndexVault::class)->handle($vault);

    $page = visitSplit(route('notes.show', [$vault, Note::query()->sole()]));

    $page->click('@today-link')
        ->assertSeeIn('@note-preview', 'Tuesday, 13 October 2026')
        ->click('@previous-day')
        ->assertSeeIn('@note-preview', 'Monday, 12 October 2026')
        ->click('@next-day')
        ->assertSeeIn('@note-preview', 'Tuesday, 13 October 2026')
        ->click('@next-day')
        ->assertSeeIn('@note-preview', 'Wednesday, 14 October 2026')
        ->assertNoJavaScriptErrors();

    expect(Note::query()->where('path', 'like', 'Daily/%')->orderBy('path')->pluck('path')->all())
        ->toBe(['Daily/2026-10-12.md', 'Daily/2026-10-13.md', 'Daily/2026-10-14.md']);
});

it("captures a thought into today's note", function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 09:30:00'));

    $vault = vaultWithFiles();

    $page = visitSplit(route('capture.create'));

    // The window keeps its own title bar; a drag region would repaint the window on every keystroke.
    $page->assertScript("[...document.querySelectorAll('*')].some((element) => getComputedStyle(element).getPropertyValue('-webkit-app-region') === 'drag')", false)
        ->fill('@capture-text', 'Remember the string')
        ->keys('@capture-text', 'Control+Enter')
        ->assertSee('Added to Tuesday, 13 October 2026.')
        ->assertValue('@capture-text', '')
        ->assertNoJavaScriptErrors();

    expect(file_get_contents($vault->path.'/Daily/2026-10-13.md'))->toEndWith("- 09:30 Remember the string\n");
});
