<?php

declare(strict_types=1);

use App\Events\QuickCaptureRequested;
use App\Services\Settings;
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
        'tree_group_colours' => false,
        ...$overrides,
    ];
}

it('shares preferences only when a page asks for them', function (): void {
    $this->get(route('vaults.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->missing('preferences')
            ->reloadOnly('preferences', fn ($reload) => $reload
                ->where('preferences.editor_font', 'mono')
                ->where('preferences.daily_folder', 'Daily')));
});

it('saves preferences, returns to the page and shares editor settings', function (): void {
    $this->from(route('vaults.index'))
        ->put(route('preferences.update'), validPreferences())
        ->assertRedirect(route('vaults.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Preferences saved.']);

    expect(config('kite.attachments.folder'))->toBe('assets')
        ->and(config('kite.daily.format'))->toBe('d.m.Y');

    $this->get(route('vaults.index'))
        ->assertInertia(fn ($page) => $page->where('kite.editor', [
            'editor_font' => 'serif',
            'editor_font_size' => 16,
            'editor_width' => 'wide',
            'autosave_ms' => 800,
        ])->where('kite.tree_group_colours', false));
});

it('validates preferences', function (array $overrides, string $error): void {
    $this->fromRoute('vaults.index')
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
    'tree group colours' => [['tree_group_colours' => 'sometimes'], 'tree_group_colours'],
]);

it('re-registers the capture shortcut in the desktop app', function (): void {
    config(['nativephp-internal.running' => true]);
    Http::fake();

    $this->withoutMiddleware(PreventRegularBrowserAccess::class)
        ->from(route('vaults.index'))
        ->put(route('preferences.update'), validPreferences(['capture_shortcut' => 'CmdOrCtrl+Alt+N']))
        ->assertRedirectToRoute('vaults.index');

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

it('leaves the appearance out of the preferences form', function (): void {
    $this->fromRoute('vaults.index')
        ->put(route('preferences.update'), validPreferences(['appearance' => 'dark']))
        ->assertRedirectToRoute('vaults.index');

    expect(resolve(Settings::class)->all()['appearance'])->toBe('system');
});
