import { useEffect, useRef } from 'react';
import { isNativeApp, onNativeEvent } from '@/lib/native-events';

export type AppCommand =
    | 'new-note'
    | 'new-from-template'
    | 'close-tab'
    | 'daily-note'
    | 'open-vault'
    | 'preferences'
    | 'search'
    | 'graph'
    | 'toggle-preview'
    | 'toggle-theme'
    | 'shortcuts'
    | 'welcome'
    | 'tasks'
    | 'flashcards'
    | 'new-view'
    | 'new-canvas'
    | 'new-search'
    | 'toggle-star'
    | 'toggle-comments'
    | 'toggle-panel'
    | 'version-history'
    | 'recently-deleted'
    | 'copy-block-link'
    | 'extract-selection'
    | 'merge-note'
    | 'focus-mode'
    | 'lock-note'
    | 'lock-vault'
    | 'change-passphrase'
    | 'recovery-key'
    | 'export-html'
    | 'export-pdf'
    | 'publish';

const COMMAND_EVENT = 'kite:command';

/**
 * Run a command as if it was chosen from the menu, e.g. from the command palette.
 */
export function runAppCommand(command: AppCommand): void {
    window.dispatchEvent(new CustomEvent(COMMAND_EVENT, { detail: command }));
}

/**
 * Keyboard equivalents of the native menu. In the desktop app the menu owns these accelerators,
 * so handling them here too would run commands twice; they are only bound in a browser.
 */
const SHORTCUTS: Record<string, AppCommand> = {
    n: 'new-note',
    w: 'close-tab',
    d: 'daily-note',
    o: 'open-vault',
    ',': 'preferences',
    k: 'search',
    g: 'graph',
    e: 'toggle-preview',
    'shift+f': 'focus-mode',
    'shift+l': 'toggle-theme',
    'shift+n': 'new-from-template',
    '/': 'shortcuts',
};

type Handlers = Partial<Record<AppCommand, () => void>>;

export function useAppCommands(handlers: Handlers): void {
    const handlersRef = useRef(handlers);

    useEffect(() => {
        handlersRef.current = handlers;
    });

    useEffect(() => {
        const run = (command: string): boolean => {
            const handler = handlersRef.current[command as AppCommand];

            handler?.();

            return handler !== undefined;
        };

        const onCommand = (event: Event) =>
            run((event as CustomEvent<string>).detail);

        window.addEventListener(COMMAND_EVENT, onCommand);

        if (isNativeApp()) {
            const stopListening = onNativeEvent<{ command: string }>(
                'App\\Events\\MenuCommandSelected',
                (payload) => {
                    run(payload.command);
                },
            );

            return () => {
                stopListening();
                window.removeEventListener(COMMAND_EVENT, onCommand);
            };
        }

        const onKeyDown = (event: KeyboardEvent) => {
            const command =
                SHORTCUTS[
                    `${event.shiftKey ? 'shift+' : ''}${event.key.toLowerCase()}`
                ];

            if (
                (event.metaKey || event.ctrlKey) &&
                !event.altKey &&
                command &&
                run(command)
            ) {
                event.preventDefault();
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => {
            window.removeEventListener('keydown', onKeyDown);
            window.removeEventListener(COMMAND_EVENT, onCommand);
        };
    }, []);
}
