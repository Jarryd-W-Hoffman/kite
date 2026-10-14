<?php

declare(strict_types=1);

use App\Events\QuickCaptureRequested;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Native\Desktop\Http\Middleware\PreventRegularBrowserAccess;

function validPreferences(array $overrides = []): array
{
    return [
        'editor_font' => 'serif',
        'editor_font_size' => 16,
        'editor_width' => 'wide',
        'autosave_ms' => 800,
        'ignored_folders' => ['node_modules', 'Archive'],
        'attachments_folder' => 'assets',
        'daily_folder' => 'Journal',
        'daily_format' => 'd.m.Y',
        'daily_template' => 'Templates/Day.md',
        'capture_shortcut' => 'CmdOrCtrl+Shift+Space',
        ...$overrides,
    ];
}

it('shows preferences', function (): void {
    $this->get(route('preferences.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('preferences/edit')
            ->where('preferences.editor_font', 'mono')
            ->where('preferences.daily_folder', 'Daily'));
});

it('saves preferences and shares editor settings', function (): void {
    $this->put(route('preferences.update'), validPreferences())
        ->assertRedirectToRoute('preferences.edit')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Preferences saved.']);

    expect(config('kite.attachments.folder'))->toBe('assets')
        ->and(config('kite.daily.format'))->toBe('d.m.Y');

    $this->get(route('preferences.edit'))
        ->assertInertia(fn ($page) => $page->where('kite.editor', [
            'editor_font' => 'serif',
            'editor_font_size' => 16,
            'editor_width' => 'wide',
            'autosave_ms' => 800,
        ]));
});

it('validates preferences', function (array $overrides, string $error): void {
    $this->fromRoute('preferences.edit')
        ->put(route('preferences.update'), validPreferences($overrides))
        ->assertSessionHasErrors($error);
})->with([
    'font' => [['editor_font' => 'comic'], 'editor_font'],
    'size' => [['editor_font_size' => 40], 'editor_font_size'],
    'autosave' => [['autosave_ms' => 50], 'autosave_ms'],
    'escaping folder' => [['daily_folder' => '../outside'], 'daily_folder'],
    'escaping ignore' => [['ignored_folders' => ['a/../b']], 'ignored_folders.0'],
    'date format' => [['daily_format' => '<script>'], 'daily_format'],
    'shortcut' => [['capture_shortcut' => 'Ctrl;rm'], 'capture_shortcut'],
]);

it('re-registers the capture shortcut in the desktop app', function (): void {
    config(['nativephp-internal.running' => true]);
    Http::fake();

    $this->withoutMiddleware(PreventRegularBrowserAccess::class)
        ->put(route('preferences.update'), validPreferences(['capture_shortcut' => 'CmdOrCtrl+Alt+N']))
        ->assertRedirectToRoute('preferences.edit');

    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'DELETE' && $request['key'] === 'CmdOrCtrl+Shift+Space');
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
        && $request['key'] === 'CmdOrCtrl+Alt+N'
        && $request['event'] === QuickCaptureRequested::class);
});

it('can disable the capture shortcut and leaves it alone when unchanged', function (): void {
    config(['nativephp-internal.running' => true]);
    Http::fake();

    $this->withoutMiddleware(PreventRegularBrowserAccess::class)
        ->put(route('preferences.update'), validPreferences(['capture_shortcut' => '']));

    Http::assertSentCount(1);

    $this->withoutMiddleware(PreventRegularBrowserAccess::class)
        ->put(route('preferences.update'), validPreferences(['capture_shortcut' => '']));

    Http::assertSentCount(1);
});
