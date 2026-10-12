<?php

declare(strict_types=1);

namespace App\Providers;

use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Window;

final class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     */
    public function boot(): void
    {
        Window::open()
            ->title('Kite')
            ->width(1440)
            ->height(900)
            ->minWidth(960)
            ->minHeight(600)
            ->titleBarHiddenInset()
            ->rememberState();
    }

    /**
     * @return array<string, string>
     */
    public function phpIni(): array
    {
        return [
            'memory_limit' => '512M',
        ];
    }
}
