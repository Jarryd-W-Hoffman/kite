import { useCallback, useEffect, useState } from 'react';
import { useNoteLinkModifiers } from '@/hooks/use-note-link-modifiers';
import { idCodec, readStored, writeStored } from '@/lib/storage';

const splitKey = (vaultId: number) => `kite:split:${vaultId}`;

/**
 * The note open in the second pane, remembered per vault. A note opens there from a link
 * followed with the split modifier, or from the command palette.
 */
export function useSplitNote(vaultId: number) {
    const [splitNoteId, setSplitNoteId] = useState<number | null>(() =>
        readStored(splitKey(vaultId), idCodec, null),
    );

    const openSplit = useCallback(
        (id: number | null) => {
            setSplitNoteId(id);
            writeStored(splitKey(vaultId), idCodec, id);
        },
        [vaultId],
    );

    const closeSplit = useCallback(() => openSplit(null), [openSplit]);

    useNoteLinkModifiers(vaultId, openSplit);

    useEffect(() => {
        const onOpenSplit = (event: Event) => {
            openSplit((event as CustomEvent<number>).detail);
        };

        window.addEventListener('kite:open-split', onOpenSplit);

        return () => window.removeEventListener('kite:open-split', onOpenSplit);
    }, [openSplit]);

    return { splitNoteId, closeSplit };
}
