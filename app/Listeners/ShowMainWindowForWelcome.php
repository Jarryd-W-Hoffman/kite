<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MenuCommandSelected;
use Native\Desktop\Facades\Window;

final readonly class ShowMainWindowForWelcome
{
    /**
     * The tray's Welcome item is usually chosen while Kite is behind other windows, so bring the
     * main window forward for the Welcome tab to open in.
     */
    public function handle(MenuCommandSelected $event): void
    {
        if ($event->command === 'welcome') {
            Window::show('main');
        }
    }
}
