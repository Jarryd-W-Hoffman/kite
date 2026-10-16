<?php

declare(strict_types=1);

use App\Events\QuickCaptureRequested;
use App\Providers\NativeAppServiceProvider;
use App\Services\Settings;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Native\Desktop\Enums\SystemThemesEnum;
use Native\Desktop\Facades\Window;
use Native\Desktop\Windows\Window as NativeWindow;

it('opens the main kite window on boot', function (): void {
    Http::fake();

    $window = new NativeWindow('main');

    $windows = Window::fake()->alwaysReturnWindows([$window]);

    new NativeAppServiceProvider()->boot();

    $windows->assertOpened('main');
    $windows->assertOpenedCount(1);

    expect($window->toArray())
        ->toMatchArray([
            'title' => 'Kite',
            'width' => 1440,
            'height' => 900,
            'minWidth' => 960,
            'minHeight' => 600,
            'rememberState' => true,
            'autoHideMenuBar' => true,
        ]);
});

it('registers the quick capture shortcut and tray menu', function (): void {
    Http::fake();

    Window::fake()->alwaysReturnWindows([new NativeWindow('main')]);

    new NativeAppServiceProvider()->boot();

    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), 'global-shortcuts')
        && $request['key'] === 'CmdOrCtrl+Shift+Space'
        && $request['event'] === QuickCaptureRequested::class);
});

it('skips the shortcut when it is disabled', function (): void {
    config(['kite.capture.shortcut' => '']);
    Http::fake();

    Window::fake()->alwaysReturnWindows([new NativeWindow('main')]);

    new NativeAppServiceProvider()->boot();

    Http::assertNotSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), 'global-shortcuts'));
});

it('applies the stored appearance to the native theme in the desktop app', function (): void {
    config(['nativephp-internal.running' => true]);
    resolve(Settings::class)->update(['appearance' => 'dark']);
    Http::fake(['*' => ['result' => 'dark']]);

    Window::fake()->alwaysReturnWindows([new NativeWindow('main')]);

    new NativeAppServiceProvider()->boot();

    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), 'system/theme')
        && $request['theme'] === SystemThemesEnum::DARK);
});

it('provides php ini directives for the native runtime', function (): void {
    expect(new NativeAppServiceProvider()->phpIni())->toBe([
        'memory_limit' => '512M',
        'upload_max_filesize' => '1G',
        'post_max_size' => '1G',
    ]);
});
