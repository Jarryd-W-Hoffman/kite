import { usePage } from '@inertiajs/react';
import type { AppCommand } from '@/hooks/use-app-commands';

/**
 * Keys that type punctuation, by the physical key's code, as Electron names them.
 */
const PUNCTUATION: Record<string, string> = {
    Comma: ',',
    Period: '.',
    Slash: '/',
    Semicolon: ';',
    Quote: "'",
    BracketLeft: '[',
    BracketRight: ']',
    Backslash: '\\',
    Backquote: '`',
    Equal: '=',
    Minus: '-',
};

/**
 * The accelerator a key press makes, written as the app menu writes them (`CmdOrCtrl+Shift+K`),
 * or null when it can't be a command's shortcut: commands need ⌘ (Ctrl elsewhere) and a letter,
 * digit or punctuation key, with Alt and Shift if you like.
 */
export function acceleratorFor(
    event: Pick<
        KeyboardEvent,
        'key' | 'code' | 'metaKey' | 'ctrlKey' | 'altKey' | 'shiftKey'
    >,
    platform: string,
): string | null {
    const mac = platform === 'darwin';

    if (
        !(mac ? event.metaKey : event.ctrlKey) ||
        (mac ? event.ctrlKey : event.metaKey)
    ) {
        return null;
    }

    // The letter typed where there is one, so other keyboard layouts match the menu; the physical
    // key where Shift or Alt change what's typed (Shift+/ types ?, Alt+A types å on macOS).
    const key = /^[a-z0-9]$/i.test(event.key)
        ? event.key.toUpperCase()
        : /^Key[A-Z]$/.test(event.code)
          ? event.code.slice(3)
          : /^Digit[0-9]$/.test(event.code)
            ? event.code.slice(5)
            : PUNCTUATION[event.code];

    if (key === undefined) {
        return null;
    }

    return [
        'CmdOrCtrl',
        ...(event.altKey ? ['Alt'] : []),
        ...(event.shiftKey ? ['Shift'] : []),
        key,
    ].join('+');
}

/**
 * Electron's longer names for modifier keys, as the short ones `formatShortcut` knows.
 */
const MODIFIERS: Record<string, string> = {
    CmdOrCtrl: 'Mod',
    CommandOrControl: 'Mod',
    Control: 'Ctrl',
    Option: 'Alt',
};

/**
 * An accelerator in the `Mod+K` form that `Kbd` and `formatShortcut` show.
 */
export function shortcutText(accelerator: string): string {
    return accelerator
        .split('+')
        .map((key) => MODIFIERS[key] ?? key)
        .join('+');
}

/**
 * The command a key press runs, if any.
 */
export function commandFor(
    event: KeyboardEvent,
    bindings: Record<string, string>,
    platform: string,
): AppCommand | null {
    const accelerator = acceleratorFor(event, platform);

    if (accelerator === null) {
        return null;
    }

    const command = Object.entries(bindings).find(
        ([, bound]) => bound === accelerator,
    );

    return command === undefined ? null : (command[0] as AppCommand);
}

let recording = false;

/**
 * Set while the shortcuts dialog waits for a new shortcut, so pressing one records it instead of
 * running its command.
 */
export function setRecordingShortcut(value: boolean): void {
    recording = value;
}

export function isRecordingShortcut(): boolean {
    return recording;
}

/**
 * Sent when a menu command's shortcut is pressed while a shortcut is being recorded. In the
 * desktop app the menu takes those keys before the page sees them.
 */
export const RECORDED_COMMAND_EVENT = 'kite:recorded-command';

/**
 * The shortcut each command has now, in the `Mod+K` form, for showing beside it. Commands whose
 * shortcut can't be changed keep the one given; a command whose shortcut was removed has none.
 */
export function useShortcuts(): (
    command: AppCommand,
    fallback?: string,
) => string | undefined {
    const { kite } = usePage().props;

    return (command, fallback) => {
        const accelerator = kite.key_bindings[command];

        if (accelerator === undefined) {
            return fallback;
        }

        return accelerator === '' ? undefined : shortcutText(accelerator);
    };
}

/**
 * Tips that show a shortcut, each with the shortcut its command has now. A tip whose command
 * has no shortcut any more is left out.
 */
export function tipsWithShortcuts<
    Tip extends { shortcut: string; command?: AppCommand },
>(tips: Tip[], shortcutFor: ReturnType<typeof useShortcuts>): Tip[] {
    return tips.flatMap((tip) => {
        const shortcut =
            tip.command === undefined
                ? tip.shortcut
                : shortcutFor(tip.command, tip.shortcut);

        return shortcut === undefined ? [] : [{ ...tip, shortcut }];
    });
}
