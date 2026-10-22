<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The keys that run Kite's menu commands: each command's default from the app menu, unless the
 * user has chosen another (or none) in the keyboard shortcuts dialog. Accelerators are written
 * as Electron writes them, e.g. `CmdOrCtrl+Shift+K`.
 */
final readonly class KeyBindings
{
    /**
     * Command, then Alt and Shift if held, then one key: a letter, digit or punctuation key.
     */
    public const string PATTERN = '/^CmdOrCtrl(\+Alt)?(\+Shift)?\+([A-Z0-9]|[,.\/;\'\[\]\\\\`=-])$/';

    /**
     * Shortcuts the editor, the app menu or the system already use, which no command can take.
     */
    public const array RESERVED = [
        'CmdOrCtrl+Z' => 'Undo',
        'CmdOrCtrl+Shift+Z' => 'Redo',
        'CmdOrCtrl+X' => 'Cut',
        'CmdOrCtrl+C' => 'Copy',
        'CmdOrCtrl+V' => 'Paste',
        'CmdOrCtrl+A' => 'Select all',
        'CmdOrCtrl+S' => 'Save now',
        'CmdOrCtrl+B' => 'Bold',
        'CmdOrCtrl+I' => 'Italic',
        'CmdOrCtrl+F' => 'Find and replace',
        'CmdOrCtrl+Alt+I' => 'Developer tools',
        'CmdOrCtrl+Alt+M' => 'Comment on the selection',
        'CmdOrCtrl+H' => 'Hide Kite',
        'CmdOrCtrl+M' => 'Minimise',
        'CmdOrCtrl+Q' => 'Quit',
        'CmdOrCtrl+R' => 'Reload',
    ];

    public function __construct(private Settings $settings)
    {
        //
    }

    /**
     * Each command's own shortcut.
     *
     * @return array<string, string>
     */
    public function defaults(): array
    {
        return array_map(fn (array $command): string => $command[1], AppMenu::COMMANDS);
    }

    /**
     * Each command's shortcut now: the user's choice where they made one, or the default. An
     * empty string means the command has none.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return array_merge($this->defaults(), $this->overrides());
    }

    /**
     * The shortcuts the user has changed, by command.
     *
     * @return array<string, string>
     */
    public function overrides(): array
    {
        $stored = $this->settings->all()['key_bindings'] ?? [];

        if (! is_array($stored)) {
            return [];
        }

        return array_filter(
            array_intersect_key($stored, AppMenu::COMMANDS),
            fn (mixed $accelerator): bool => $accelerator === '' || (is_string($accelerator) && preg_match(self::PATTERN, $accelerator) === 1),
        );
    }

    /**
     * What already uses a shortcut, other than the given command: a command's name or a reserved
     * shortcut's, or null when it's free.
     *
     * @param  array<string, string>  $bindings
     */
    public function usedBy(string $accelerator, string $command, array $bindings): ?string
    {
        if (isset(self::RESERVED[$accelerator])) {
            return self::RESERVED[$accelerator];
        }

        foreach ($bindings as $other => $taken) {
            if ($other !== $command && $taken === $accelerator) {
                return mb_rtrim(AppMenu::COMMANDS[$other][0], '…');
            }
        }

        return null;
    }
}
