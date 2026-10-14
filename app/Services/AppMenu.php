<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\MenuCommandSelected;
use App\Events\QuickCaptureRequested;
use Native\Desktop\Facades\Menu;
use Native\Desktop\Menu\Items\Label;

/**
 * The native application menu. Commands are handled by the window, so the menu owns their accelerators.
 */
final readonly class AppMenu
{
    public const array COMMANDS = [
        'new-note' => ['New note', 'CmdOrCtrl+N'],
        'new-from-template' => ['New note from template…', 'CmdOrCtrl+Shift+N'],
        'close-tab' => ['Close tab', 'CmdOrCtrl+W'],
        'daily-note' => ["Today's note", 'CmdOrCtrl+D'],
        'open-vault' => ['Open vault…', 'CmdOrCtrl+O'],
        'preferences' => ['Preferences…', 'CmdOrCtrl+,'],
        'search' => ['Search…', 'CmdOrCtrl+K'],
        'graph' => ['Graph view', 'CmdOrCtrl+G'],
        'toggle-preview' => ['Toggle preview', 'CmdOrCtrl+E'],
        'focus-mode' => ['Focus mode', 'CmdOrCtrl+Shift+F'],
        'toggle-theme' => ['Toggle dark mode', 'CmdOrCtrl+Shift+L'],
        'shortcuts' => ['Keyboard shortcuts', 'CmdOrCtrl+/'],
    ];

    public function register(): void
    {
        Menu::create(
            Menu::app(),
            Menu::make(
                $this->command('new-note'),
                $this->command('new-from-template'),
                $this->command('daily-note'),
                Menu::label('Quick capture…')->event(QuickCaptureRequested::class),
                Menu::separator(),
                $this->command('open-vault'),
                Menu::separator(),
                $this->command('preferences'),
                Menu::separator(),
                $this->command('close-tab'),
            )->label('File'),
            Menu::edit(),
            Menu::make(
                $this->command('search'),
                $this->command('graph'),
                $this->command('toggle-preview'),
                $this->command('focus-mode'),
                $this->command('toggle-theme'),
                Menu::separator(),
                $this->command('shortcuts'),
                Menu::separator(),
                Menu::reload(),
                Menu::devTools(),
                Menu::separator(),
                Menu::fullscreen(),
            )->label('View'),
            Menu::window(),
        );
    }

    private function command(string $id): Label
    {
        [$label, $accelerator] = self::COMMANDS[$id];

        $item = Menu::label($label);
        $item->id($id)->event(MenuCommandSelected::class)->hotkey($accelerator);

        return $item;
    }
}
