import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import {
    commandFor,
    isRecordingShortcut,
    RECORDED_COMMAND_EVENT,
} from '@/lib/key-bindings';
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

type Handlers = Partial<Record<AppCommand, () => void>>;

/**
 * Run commands chosen from the native menu, the command palette or, in a browser, by their
 * shortcuts. In the desktop app the menu owns the shortcuts, so handling them here too would run
 * commands twice.
 */
export function useAppCommands(handlers: Handlers): void {
    const { kite } = usePage().props;
    const handlersRef = useRef(handlers);
    const keysRef = useRef({
        bindings: kite.key_bindings,
        platform: kite.platform,
    });

    useEffect(() => {
        handlersRef.current = handlers;
        keysRef.current = {
            bindings: kite.key_bindings,
            platform: kite.platform,
        };
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
                    if (isRecordingShortcut()) {
                        window.dispatchEvent(
                            new CustomEvent(RECORDED_COMMAND_EVENT, {
                                detail: payload.command,
                            }),
                        );

                        return;
                    }

                    run(payload.command);
                },
            );

            return () => {
                stopListening();
                window.removeEventListener(COMMAND_EVENT, onCommand);
            };
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (isRecordingShortcut()) {
                return;
            }

            const command = commandFor(
                event,
                keysRef.current.bindings,
                keysRef.current.platform,
            );

            if (command !== null && run(command)) {
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
