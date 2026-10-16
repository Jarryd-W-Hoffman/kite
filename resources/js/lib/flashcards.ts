import { index as flashcardsIndex } from '@/routes/flashcards';

/** Today's date on this computer (Y-m-d): cards fall due at the reviewer's midnight. */
export function localToday(): string {
    const now = new Date();

    return [
        now.getFullYear(),
        String(now.getMonth() + 1).padStart(2, '0'),
        String(now.getDate()).padStart(2, '0'),
    ].join('-');
}

/** The flashcards page for today: the decks, or a study session (of one deck, or all). */
export function flashcardsUrl(
    vaultId: number,
    study?: { deck: string | null },
): string {
    return flashcardsIndex(vaultId, {
        query: {
            today: localToday(),
            ...(study ? { study: 1 } : {}),
            ...(study?.deck ? { deck: study.deck } : {}),
        },
    }).url;
}

/** How long until a card comes back, for its grade buttons: "Today", "1 day", "3 weeks"… */
export function describeInterval(days: number): string {
    if (days <= 0) {
        return 'Today';
    }

    if (days < 14) {
        return days === 1 ? '1 day' : `${days} days`;
    }

    if (days < 60) {
        return `${Math.round(days / 7)} weeks`;
    }

    if (days < 365) {
        return `${Math.round(days / 30)} months`;
    }

    const years = Math.round((days / 365) * 10) / 10;

    return years === 1 ? '1 year' : `${years} years`;
}
