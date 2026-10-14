import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { requestNewTab } from '@/lib/tabs';

const NOTE_PATH = /^\/vaults\/(\d+)\/notes\/(\d+)\/?$/;

/**
 * Cmd/Ctrl+click on any note link opens it in a new tab; adding Shift opens it in the split pane.
 * Runs in the capture phase so it wins over Inertia links and the preview's own click handling.
 */
export function useNoteLinkModifiers(
    vaultId: number,
    onOpenSplit: (noteId: number) => void,
): void {
    const onOpenSplitRef = useRef(onOpenSplit);

    useEffect(() => {
        onOpenSplitRef.current = onOpenSplit;
    });

    useEffect(() => {
        const onClick = (event: MouseEvent) => {
            if (!(event.metaKey || event.ctrlKey) || event.button !== 0) {
                return;
            }

            const anchor =
                event.target instanceof Element
                    ? event.target.closest<HTMLAnchorElement>('a[href]')
                    : null;
            const url = anchor
                ? new URL(anchor.href, window.location.href)
                : null;
            const match = url ? NOTE_PATH.exec(url.pathname) : null;

            if (!url || !match || Number(match[1]) !== vaultId) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            if (event.shiftKey) {
                onOpenSplitRef.current(Number(match[2]));
            } else {
                requestNewTab();
                router.visit(url.pathname + url.hash);
            }
        };

        document.addEventListener('click', onClick, true);

        return () => document.removeEventListener('click', onClick, true);
    }, [vaultId]);
}
