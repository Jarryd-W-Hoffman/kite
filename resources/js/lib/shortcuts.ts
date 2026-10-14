/**
 * Display a shortcut like `Mod+Shift+K` for the current platform: symbols on macOS, words elsewhere.
 */
export function formatShortcut(shortcut: string, platform: string): string {
    const mac = platform === 'darwin';
    const names: Record<string, string> = mac
        ? { Mod: '⌘', Shift: '⇧', Alt: '⌥', Ctrl: '⌃' }
        : { Mod: 'Ctrl', Shift: 'Shift', Alt: 'Alt', Ctrl: 'Ctrl' };

    return shortcut
        .split('+')
        .map((key) => names[key] ?? key)
        .join(mac ? '' : '+');
}
