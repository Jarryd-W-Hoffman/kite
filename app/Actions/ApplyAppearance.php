<?php

declare(strict_types=1);

namespace App\Actions;

use Native\Desktop\Enums\SystemThemesEnum;
use Native\Desktop\Facades\System;

/**
 * Match the desktop window's native theme (title bar, menus, scrollbars) to the chosen appearance.
 */
final readonly class ApplyAppearance
{
    public function handle(string $appearance): void
    {
        if (! config('nativephp-internal.running')) {
            return;
        }

        System::theme(SystemThemesEnum::tryFrom($appearance) ?? SystemThemesEnum::SYSTEM);
    }
}
