<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\ApplyAppearance;
use App\Events\QuickCaptureRequested;
use App\Services\AppMenu;
use App\Services\Settings;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\GlobalShortcut;
use Native\Desktop\Facades\Menu;
use Native\Desktop\Facades\MenuBar;
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
            ->hideMenu()
            ->rememberState();

        $appearance = resolve(Settings::class)->all()['appearance'];
        resolve(ApplyAppearance::class)->handle(is_string($appearance) ? $appearance : 'system');

        $shortcut = config('kite.capture.shortcut');

        if (is_string($shortcut) && $shortcut !== '') {
            GlobalShortcut::key($shortcut)->event(QuickCaptureRequested::class)->register();
        }

        resolve(AppMenu::class)->register();

        MenuBar::create()
            ->onlyShowContextMenu()
            ->showDockIcon()
            ->tooltip('Kite')
            ->withContextMenu(Menu::make(
                Menu::label('Quick capture…')->event(QuickCaptureRequested::class),
                Menu::separator(),
                Menu::quit('Quit Kite'),
            ));
    }

    /**
     * @return array<string, string>
     */
    public function phpIni(): array
    {
        return [
            'memory_limit' => '512M',
            // Room for big uploads, such as a Notion export.
            'upload_max_filesize' => '1G',
            'post_max_size' => '1G',
        ];
    }
}
