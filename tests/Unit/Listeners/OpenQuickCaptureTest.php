<?php

declare(strict_types=1);

use App\Events\QuickCaptureRequested;
use App\Listeners\OpenQuickCapture;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Native\Desktop\Facades\Window;
use Native\Desktop\Windows\Window as NativeWindow;

it('opens a small always-on-top capture window', function (): void {
    Http::fake();

    $window = new NativeWindow(OpenQuickCapture::WINDOW);
    $windows = Window::fake()->alwaysReturnWindows([$window]);

    new OpenQuickCapture()->handle();

    $windows->assertOpened(OpenQuickCapture::WINDOW);

    expect($window->toArray())->toMatchArray([
        'title' => 'Quick capture',
        'width' => 560,
        'height' => 260,
        'resizable' => false,
        'alwaysOnTop' => true,
        'url' => route('capture.create'),
    ]);
});

it('keeps whatever payload NativePHP sends', function (): void {
    expect(new QuickCaptureRequested('a', key: 'b')->payload)->toBe([0 => 'a', 'key' => 'b']);
});

it('is discovered as the listener for quick capture requests', function (): void {
    Event::fake();

    Event::assertListening(QuickCaptureRequested::class, OpenQuickCapture::class);
});
