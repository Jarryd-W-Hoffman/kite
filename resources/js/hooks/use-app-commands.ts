import { useEffect, useRef } from 'react';
import { isNativeApp, onNativeEvent } from '@/lib/native-events';

export type AppCommand =
    | 'new-note'
    | 'daily-note'
    | 'open-vault'
    | 'preferences'
    | 'search'
    | 'graph'
    | 'toggle-preview';

/**
 * Keyboard equivalents of the native menu. In the desktop app the menu owns these accelerators,
 * so handling them here too would run commands twice; they are only bound in a browser.
 */
const SHORTCUTS: Record<string, AppCommand> = {
    n: 'new-note',
    d: 'daily-note',
    o: 'open-vault',
    ',': 'preferences',
    k: 'search',
    g: 'graph',
    e: 'toggle-preview',
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

        if (isNativeApp()) {
            return onNativeEvent<{ command: string }>(
                'App\\Events\\MenuCommandSelected',
                (payload) => {
                    run(payload.command);
                },
            );
        }

        const onKeyDown = (event: KeyboardEvent) => {
            const command = SHORTCUTS[event.key.toLowerCase()];

            if (
                (event.metaKey || event.ctrlKey) &&
                !event.shiftKey &&
                !event.altKey &&
                command &&
                run(command)
            ) {
                event.preventDefault();
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);
}
