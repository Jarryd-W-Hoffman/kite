<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Listeners\OpenQuickCapture;
use App\Models\Note;
use Carbon\CarbonImmutable;
use Native\Desktop\Facades\Window;
use Native\Desktop\Http\Middleware\PreventRegularBrowserAccess;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 09:30:00'));
});

it("opens today's note or a given day", function (): void {
    $vault = vaultWithFiles();

    $this->post(route('daily-notes.store', $vault))
        ->assertRedirectToRoute('notes.show', [$vault, Note::query()->where('path', 'Daily/2026-10-13.md')->sole()]);

    $this->post(route('daily-notes.store', $vault), ['date' => '2026-10-12'])
        ->assertRedirectToRoute('notes.show', [$vault, Note::query()->where('path', 'Daily/2026-10-12.md')->sole()]);

    $this->post(route('daily-notes.store', $vault), ['date' => 'yesterday'])->assertSessionHasErrors('date');
});

it('shows the capture window for the last opened vault', function (): void {
    $vault = vaultWithFiles();

    $this->get(route('capture.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('capture/create')->where('vault', ['id' => $vault->id, 'name' => $vault->name]));
});

it("appends captures to today's note", function (): void {
    $vault = vaultWithFiles();

    $this->post(route('capture.store'), ['text' => 'Buy string'])
        ->assertRedirectToRoute('capture.create')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Added to Tuesday, 13 October 2026.']);

    expect(file_get_contents($vault->path.'/Daily/2026-10-13.md'))->toEndWith("- 09:30 Buy string\n");
});

it('closes the native capture window after capturing', function (): void {
    config(['nativephp-internal.running' => true]);

    $windows = Window::fake();
    vaultWithFiles();

    $this->withoutMiddleware(PreventRegularBrowserAccess::class)
        ->post(route('capture.store'), ['text' => 'Native capture'])
        ->assertRedirectToRoute('capture.create');

    $windows->assertClosed(OpenQuickCapture::WINDOW);
});

it('validates captures', function (): void {
    $this->post(route('capture.store'), ['text' => 'No vault yet'])->assertSessionHasErrors(['text' => 'Open a vault in Kite first.']);

    vaultWithFiles();

    $this->post(route('capture.store'), ['text' => ''])->assertSessionHasErrors('text');
});

it('shares the dates that have daily notes for the calendar', function (): void {
    $vault = vaultWithFiles(['Daily/2026-10-02.md' => '# a', 'Daily/2026-10-14.md' => '# b', 'Daily/notes.md' => '# c', 'Home.md' => '# Home']);
    resolve(IndexVault::class)->handle($vault);

    $this->get(route('notes.show', [$vault, Note::query()->where('path', 'Home.md')->sole()]))
        ->assertInertia(fn ($page) => $page->where('daily_dates', ['2026-10-02', '2026-10-14']));
});
