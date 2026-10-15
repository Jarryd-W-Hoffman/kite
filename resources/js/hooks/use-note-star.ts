import { router } from '@inertiajs/react';
import { useState } from 'react';
import { update as updateStar } from '@/routes/note-star';
import type { VaultSummary } from '@/types';

/**
 * Whether a note is starred (a favourite), changed optimistically and put back if saving fails.
 */
export function useNoteStar(
    vault: VaultSummary,
    noteId: number,
    initial: boolean,
) {
    const [starred, setStarred] = useState(initial);

    const toggleStar = () => {
        const next = !starred;

        setStarred(next);
        router.put(
            updateStar({ vault: vault.id, note: noteId }).url,
            { starred: next },
            {
                only: ['favourites'],
                preserveScroll: true,
                preserveState: true,
                onError: () => setStarred(!next),
            },
        );
    };

    return { starred, toggleStar };
}
