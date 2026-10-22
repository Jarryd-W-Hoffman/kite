import { booleanCodec, readStored, writeStored } from '@/lib/storage';
import type { Codec } from '@/lib/storage';
import { show as showNote } from '@/routes/notes';
import { show as showWelcome } from '@/routes/welcome';

/**
 * A note open in a tab. Pinned tabs stay at the front, aren't replaced by other notes and
 * aren't closed by Mod+W.
 */
export type NoteTab = {
    kind: 'note';
    id: number;
    title: string;
    pinned?: boolean;
};

/**
 * A built-in page open in a tab, such as Welcome.
 */
export type PageTab = {
    kind: 'page';
    page: 'welcome';
    title: string;
    pinned?: boolean;
};

/**
 * Anything that can be open in a tab.
 */
export type Tab = NoteTab | PageTab;

export type TabState = {
    tabs: Tab[];
    /** The key of the active tab. */
    active: string | null;
};

const NEW_TAB_FLAG = 'kite:new-tab';

function key(vaultId: number): string {
    return `kite:tabs:${vaultId}`;
}

/**
 * A tab's identity within a vault, e.g. `note:12`.
 */
export function tabKey(tab: Tab): string {
    return tab.kind === 'note' ? `note:${tab.id}` : `page:${tab.page}`;
}

/**
 * Where a tab leads.
 */
export function tabHref(vaultId: number, tab: Tab): string {
    return tab.kind === 'note'
        ? showNote({ vault: vaultId, note: tab.id }).url
        : showWelcome(vaultId).url;
}

function isPinned(value: unknown): boolean {
    return value === undefined || typeof value === 'boolean';
}

/**
 * A stored tab, in the current form or the original note-only one (`{ id, title }`).
 */
function readTab(value: unknown): Tab | null {
    const tab = value as Record<string, unknown> | null;

    if (
        typeof tab !== 'object' ||
        tab === null ||
        typeof tab.title !== 'string' ||
        !isPinned(tab.pinned)
    ) {
        return null;
    }

    const pinned =
        tab.pinned === undefined ? {} : { pinned: tab.pinned as boolean };

    if (tab.kind === 'page' && tab.page === 'welcome') {
        return { kind: 'page', page: 'welcome', title: tab.title, ...pinned };
    }

    if (
        (tab.kind === undefined || tab.kind === 'note') &&
        typeof tab.id === 'number'
    ) {
        return { kind: 'note', id: tab.id, title: tab.title, ...pinned };
    }

    return null;
}

const tabStateCodec: Codec<TabState> = {
    parse: (raw) => {
        try {
            const value = JSON.parse(raw) as Record<string, unknown> | null;

            if (
                typeof value !== 'object' ||
                value === null ||
                !Array.isArray(value.tabs)
            ) {
                return undefined;
            }

            const tabs = value.tabs.map(readTab);

            if (tabs.some((tab) => tab === null)) {
                return undefined;
            }

            // The original form stored the active note's id.
            const active =
                typeof value.active === 'number'
                    ? `note:${value.active}`
                    : value.active;

            return active === null || typeof active === 'string'
                ? { tabs: tabs as Tab[], active }
                : undefined;
        } catch {
            return undefined;
        }
    },
    serialize: (value) => JSON.stringify(value),
};

const flagCodec = booleanCodec;

export function readTabs(vaultId: number): TabState {
    return readStored(key(vaultId), tabStateCodec, { tabs: [], active: null });
}

function writeTabs(vaultId: number, state: TabState): TabState {
    writeStored(key(vaultId), tabStateCodec, state);

    return state;
}

/**
 * Ask the next note visit to open in a new tab rather than replacing the current one.
 */
export function requestNewTab(): void {
    writeStored(NEW_TAB_FLAG, flagCodec, true, 'session');
}

/**
 * Record a visit to a tab: focus it, open it in a new tab when requested, or replace the active tab.
 */
export function visitTab(vaultId: number, visited: Tab): TabState {
    const state = readTabs(vaultId);
    const wantsNewTab = readStored(NEW_TAB_FLAG, flagCodec, false, 'session');
    writeStored(NEW_TAB_FLAG, flagCodec, null, 'session');

    const visitedKey = tabKey(visited);
    const existing = state.tabs.findIndex((tab) => tabKey(tab) === visitedKey);
    let tabs = [...state.tabs];

    if (existing !== -1) {
        tabs[existing] = { ...visited, pinned: tabs[existing].pinned };
    } else {
        const activeIndex = tabs.findIndex(
            (tab) => tabKey(tab) === state.active,
        );

        // A pinned tab keeps its note; the new one opens beside it instead.
        if (wantsNewTab || activeIndex === -1 || tabs[activeIndex].pinned) {
            tabs.splice(activeIndex + 1 || tabs.length, 0, visited);
        } else {
            tabs[activeIndex] = visited;
        }
    }

    tabs = tabs.filter(
        (tab, index) =>
            tabs.findIndex((other) => tabKey(other) === tabKey(tab)) === index,
    );

    return writeTabs(vaultId, { tabs, active: visitedKey });
}

/**
 * Bring tabs in line with the vault's notes: drop tabs whose note is gone (deleted, or replaced
 * while Kite was closed) so they don't lead nowhere, and update renamed notes' titles. The tab
 * being shown is always kept.
 */
export function reconcileTabs(
    vaultId: number,
    notes: { id: number; title: string }[],
    keep: string,
): TabState {
    const state = readTabs(vaultId);
    const titles = new Map(notes.map((note) => [note.id, note.title]));

    const tabs = state.tabs.flatMap((tab): Tab[] => {
        if (tab.kind !== 'note') {
            return [tab];
        }

        const title = titles.get(tab.id);

        if (title === undefined) {
            return tabKey(tab) === keep ? [tab] : [];
        }

        return [{ ...tab, title }];
    });

    return writeTabs(vaultId, {
        tabs,
        active: tabs.some((tab) => tabKey(tab) === state.active)
            ? state.active
            : keep,
    });
}

/**
 * Close a tab, returning the new state and the tab to show next (if the active one closed).
 */
export function closeTab(
    vaultId: number,
    closing: string,
): { state: TabState; next: Tab | null } {
    const state = readTabs(vaultId);
    const index = state.tabs.findIndex((tab) => tabKey(tab) === closing);

    if (state.tabs[index]?.pinned) {
        return { state, next: null };
    }

    const tabs = state.tabs.filter((tab) => tabKey(tab) !== closing);

    if (state.active !== closing) {
        return { state: writeTabs(vaultId, { ...state, tabs }), next: null };
    }

    const next = tabs[Math.min(index, tabs.length - 1)] ?? null;

    return {
        state: writeTabs(vaultId, {
            tabs,
            active: next === null ? null : tabKey(next),
        }),
        next,
    };
}

/**
 * Pin or unpin a tab. Pinned tabs move to the front, in the order they were pinned.
 */
export function togglePin(vaultId: number, pinning: string): TabState {
    const state = readTabs(vaultId);
    const tabs = state.tabs.map((tab) =>
        tabKey(tab) === pinning ? { ...tab, pinned: !tab.pinned } : tab,
    );

    return writeTabs(vaultId, {
        ...state,
        tabs: [
            ...tabs.filter((tab) => tab.pinned),
            ...tabs.filter((tab) => !tab.pinned),
        ],
    });
}
