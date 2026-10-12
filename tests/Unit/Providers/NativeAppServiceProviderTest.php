<?php

declare(strict_types=1);

use App\Providers\NativeAppServiceProvider;
use Illuminate\Support\Facades\Http;
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
        ]);
});

it('provides php ini directives for the native runtime', function (): void {
    expect(new NativeAppServiceProvider()->phpIni())->toBe([
        'memory_limit' => '512M',
    ]);
});
