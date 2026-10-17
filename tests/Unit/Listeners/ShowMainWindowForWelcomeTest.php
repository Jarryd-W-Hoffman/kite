<?php

declare(strict_types=1);

use App\Events\MenuCommandSelected;
use App\Listeners\ShowMainWindowForWelcome;
use Illuminate\Support\Facades\Http;
use Native\Desktop\Facades\Window;

it('brings the main window forward for the tray Welcome item', function (): void {
    $windows = Window::fake();

    resolve(ShowMainWindowForWelcome::class)->handle(new MenuCommandSelected(['id' => 'welcome']));

    $windows->assertShown('main');
});

it('leaves the window alone for other menu commands', function (): void {
    $windows = Window::fake();

    resolve(ShowMainWindowForWelcome::class)->handle(new MenuCommandSelected(['id' => 'graph']));

    $windows->assertShownCount(0);
});

it('runs when a menu command is chosen', function (): void {
    Http::fake();
    $windows = Window::fake();

    MenuCommandSelected::dispatch(['id' => 'welcome']);

    $windows->assertShown('main');
});
