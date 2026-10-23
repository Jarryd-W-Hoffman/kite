import { describe, expect, it } from 'vite-plus/test';
import { acceleratorFor, commandFor, shortcutText } from '@/lib/key-bindings';

type Press = Parameters<typeof acceleratorFor>[0];

const press = (key: string, code: string, held: Partial<Press> = {}) =>
    ({
        key,
        code,
        metaKey: false,
        ctrlKey: false,
        altKey: false,
        shiftKey: false,
        ...held,
    }) satisfies Press;

describe('acceleratorFor', () => {
    it('writes a press the way the app menu writes shortcuts', () => {
        expect(
            acceleratorFor(press('k', 'KeyK', { ctrlKey: true }), 'linux'),
        ).toBe('CmdOrCtrl+K');
        expect(
            acceleratorFor(press('k', 'KeyK', { metaKey: true }), 'darwin'),
        ).toBe('CmdOrCtrl+K');
        expect(
            acceleratorFor(
                press('K', 'KeyK', {
                    ctrlKey: true,
                    altKey: true,
                    shiftKey: true,
                }),
                'windows',
            ),
        ).toBe('CmdOrCtrl+Alt+Shift+K');
    });

    it('names the key, not what Shift or Alt make it type', () => {
        expect(
            acceleratorFor(
                press('?', 'Slash', { ctrlKey: true, shiftKey: true }),
                'linux',
            ),
        ).toBe('CmdOrCtrl+Shift+/');
        expect(
            acceleratorFor(
                press('å', 'KeyA', { metaKey: true, altKey: true }),
                'darwin',
            ),
        ).toBe('CmdOrCtrl+Alt+A');
        expect(
            acceleratorFor(
                press('!', 'Digit1', { ctrlKey: true, shiftKey: true }),
                'linux',
            ),
        ).toBe('CmdOrCtrl+Shift+1');
        expect(
            acceleratorFor(press(',', 'Comma', { ctrlKey: true }), 'linux'),
        ).toBe('CmdOrCtrl+,');
    });

    it('follows the keyboard layout for letters, as the menu does', () => {
        // The key that types K on a Dvorak keyboard is where V is on a QWERTY one.
        expect(
            acceleratorFor(press('k', 'KeyV', { ctrlKey: true }), 'linux'),
        ).toBe('CmdOrCtrl+K');
    });

    it("turns down presses that can't be a command's shortcut", () => {
        expect(acceleratorFor(press('k', 'KeyK'), 'linux')).toBeNull();
        expect(
            acceleratorFor(press('k', 'KeyK', { shiftKey: true }), 'linux'),
        ).toBeNull();
        expect(
            acceleratorFor(press('k', 'KeyK', { ctrlKey: true }), 'darwin'),
        ).toBeNull();
        expect(
            acceleratorFor(
                press('k', 'KeyK', { metaKey: true, ctrlKey: true }),
                'darwin',
            ),
        ).toBeNull();
        expect(
            acceleratorFor(
                press('Control', 'ControlLeft', { ctrlKey: true }),
                'linux',
            ),
        ).toBeNull();
        expect(
            acceleratorFor(press(' ', 'Space', { ctrlKey: true }), 'linux'),
        ).toBeNull();
        expect(
            acceleratorFor(press('F5', 'F5', { ctrlKey: true }), 'linux'),
        ).toBeNull();
    });
});

describe('commandFor', () => {
    const bindings = {
        search: 'CmdOrCtrl+Shift+P',
        graph: '',
        'new-note': 'CmdOrCtrl+N',
    };

    it('finds the command a press runs', () => {
        expect(
            commandFor(
                press('P', 'KeyP', {
                    ctrlKey: true,
                    shiftKey: true,
                }) as KeyboardEvent,
                bindings,
                'linux',
            ),
        ).toBe('search');
        expect(
            commandFor(
                press('n', 'KeyN', { ctrlKey: true }) as KeyboardEvent,
                bindings,
                'linux',
            ),
        ).toBe('new-note');
        expect(
            commandFor(
                press('k', 'KeyK', { ctrlKey: true }) as KeyboardEvent,
                bindings,
                'linux',
            ),
        ).toBeNull();
        expect(
            commandFor(press('p', 'KeyP') as KeyboardEvent, bindings, 'linux'),
        ).toBeNull();
    });
});

describe('shortcutText', () => {
    it('writes accelerators the way Kbd shows them', () => {
        expect(shortcutText('CmdOrCtrl+Shift+K')).toBe('Mod+Shift+K');
    });

    it("shortens Electron's longer modifier names", () => {
        expect(shortcutText('CommandOrControl+Option+Space')).toBe(
            'Mod+Alt+Space',
        );
        expect(shortcutText('Control+Shift+K')).toBe('Ctrl+Shift+K');
        expect(shortcutText('Alt+Shift+K')).toBe('Alt+Shift+K');
    });
});
