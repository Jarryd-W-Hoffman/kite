/**
 * Bridge NativePHP's window events into a standard EventTarget.
 *
 * `window.Native.on()` registers IPC listeners that can never be removed, so it is
 * called once per event name here and components subscribe/unsubscribe normally.
 */
type NativeBridge = {
    on: (
        event: string,
        callback: (payload: unknown, event: string) => void,
    ) => void;
};

declare global {
    interface Window {
        Native?: NativeBridge;
    }
}

const target = new EventTarget();
const bridged = new Set<string>();

export function isNativeApp(): boolean {
    return (
        typeof window !== 'undefined' && typeof window.Native?.on === 'function'
    );
}

export function onNativeEvent<T>(
    event: string,
    callback: (payload: T) => void,
): () => void {
    if (!isNativeApp()) {
        return () => undefined;
    }

    if (!bridged.has(event)) {
        bridged.add(event);
        window.Native?.on(event, (payload) => {
            target.dispatchEvent(new CustomEvent(event, { detail: payload }));
        });
    }

    const listener = (dispatched: Event) => {
        callback((dispatched as CustomEvent<T>).detail);
    };

    target.addEventListener(event, listener);

    return () => target.removeEventListener(event, listener);
}
