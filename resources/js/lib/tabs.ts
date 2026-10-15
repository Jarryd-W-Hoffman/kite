import {
    booleanCodec,
    jsonCodec,
    readStored,
    writeStored,
} from '@/lib/storage';

export type Tab = {
    id: number;
    title: string;
    /** Pinned tabs stay at the front, aren't replaced by other notes and aren't closed by Mod+W. */
    pinned?: boolean;
};

export type TabState = {
    tabs: Tab[];
    active: number | null;
};

const NEW_TAB_FLAG = 'kite:new-tab';

function key(vaultId: number): string {
    return `kite:tabs:${vaultId}`;
}

function isTab(value: unknown): value is Tab {
    const tab = value as Partial<Tab> | null;

    return (
        typeof tab === 'object' &&
        tab !== null &&
        typeof tab.id === 'number' &&
        typeof tab.title === 'string' &&
        (tab.pinned === undefined || typeof tab.pinned === 'boolean')
    );
}

const tabStateCodec = jsonCodec((value): value is TabState => {
    const state = value as Partial<TabState> | null;

    return (
        typeof state === 'object' &&
        state !== null &&
        Array.isArray(state.tabs) &&
        state.tabs.every(isTab) &&
        (state.active === null || typeof state.active === 'number')
    );
});

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
 * Record a visit to a note: focus its tab, open it in a new tab when requested, or replace the active tab.
 */
export function visitTab(vaultId: number, note: Tab): TabState {
    const state = readTabs(vaultId);
    const wantsNewTab = readStored(NEW_TAB_FLAG, flagCodec, false, 'session');
    writeStored(NEW_TAB_FLAG, flagCodec, null, 'session');

    const existing = state.tabs.findIndex((tab) => tab.id === note.id);
    let tabs = [...state.tabs];

    if (existing !== -1) {
        tabs[existing] = { ...note, pinned: tabs[existing].pinned };
    } else {
        const activeIndex = tabs.findIndex((tab) => tab.id === state.active);

        // A pinned tab keeps its note; the new one opens beside it instead.
        if (wantsNewTab || activeIndex === -1 || tabs[activeIndex].pinned) {
            tabs.splice(activeIndex + 1 || tabs.length, 0, note);
        } else {
            tabs[activeIndex] = note;
        }
    }

    tabs = tabs.filter(
        (tab, index) =>
            tabs.findIndex((other) => other.id === tab.id) === index,
    );

    return writeTabs(vaultId, { tabs, active: note.id });
}

/**
 * Close a tab, returning the new state and the tab to show next (if the active one closed).
 */
export function closeTab(
    vaultId: number,
    id: number,
): { state: TabState; next: number | null } {
    const state = readTabs(vaultId);
    const index = state.tabs.findIndex((tab) => tab.id === id);

    if (state.tabs[index]?.pinned) {
        return { state, next: null };
    }

    const tabs = state.tabs.filter((tab) => tab.id !== id);

    if (state.active !== id) {
        return { state: writeTabs(vaultId, { ...state, tabs }), next: null };
    }

    const next = tabs[Math.min(index, tabs.length - 1)]?.id ?? null;

    return { state: writeTabs(vaultId, { tabs, active: next }), next };
}

/**
 * Pin or unpin a tab. Pinned tabs move to the front, in the order they were pinned.
 */
export function togglePin(vaultId: number, id: number): TabState {
    const state = readTabs(vaultId);
    const tabs = state.tabs.map((tab) =>
        tab.id === id ? { ...tab, pinned: !tab.pinned } : tab,
    );

    return writeTabs(vaultId, {
        ...state,
        tabs: [
            ...tabs.filter((tab) => tab.pinned),
            ...tabs.filter((tab) => !tab.pinned),
        ],
    });
}
