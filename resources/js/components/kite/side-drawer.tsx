import type { ReactNode } from 'react';

/**
 * A side panel: in line beside the note, or floating over it (with a click-away backdrop) when
 * the window is too narrow for both.
 */
export function SideDrawer({
    overlay,
    onClose,
    children,
}: {
    overlay: boolean;
    onClose: () => void;
    children: ReactNode;
}) {
    if (!overlay) {
        return children;
    }

    return (
        <>
            <div
                className="absolute inset-0 z-30 bg-black/10 dark:bg-black/30"
                onClick={onClose}
                aria-hidden
            />
            <div
                className="absolute inset-y-0 right-0 z-40 flex bg-background shadow-kite-lg"
                data-test="side-drawer"
            >
                {children}
            </div>
        </>
    );
}
