<?php

declare(strict_types=1);

namespace App\Listeners;

use Native\Desktop\Facades\Window;

final readonly class OpenQuickCapture
{
    public const string WINDOW = 'capture';

    public function handle(): void
    {
        Window::open(self::WINDOW)
            ->route('capture.create')
            ->title('Quick capture')
            ->width(560)
            ->height(260)
            ->resizable(false)
            ->minimizable(false)
            ->maximizable(false)
            ->alwaysOnTop()
            ->hideMenu();
    }
}
