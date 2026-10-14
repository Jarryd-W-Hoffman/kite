export type Tab = {
    id: number;
    title: string;
};

export type TabState = {
    tabs: Tab[];
    active: number | null;
};

const NEW_TAB_FLAG = 'kite:new-tab';

function key(vaultId: number): string {
    return `kite:tabs:${vaultId}`;
}

export function readTabs(vaultId: number): TabState {
    try {
        const state = JSON.parse(
            localStorage.getItem(key(vaultId)) ?? 'null',
        ) as TabState | null;

        if (state && Array.isArray(state.tabs)) {
            return state;
        }
    } catch {
        // Fall through to an empty set of tabs.
    }

    return { tabs: [], active: null };
}

function writeTabs(vaultId: number, state: TabState): TabState {
    localStorage.setItem(key(vaultId), JSON.stringify(state));

    return state;
}

/**
 * Ask the next note visit to open in a new tab rather than replacing the current one.
 */
export function requestNewTab(): void {
    sessionStorage.setItem(NEW_TAB_FLAG, '1');
}

/**
 * Record a visit to a note: focus its tab, open it in a new tab when requested, or replace the active tab.
 */
export function visitTab(vaultId: number, note: Tab): TabState {
    const state = readTabs(vaultId);
    const wantsNewTab = sessionStorage.getItem(NEW_TAB_FLAG) === '1';
    sessionStorage.removeItem(NEW_TAB_FLAG);

    const existing = state.tabs.findIndex((tab) => tab.id === note.id);
    let tabs = [...state.tabs];

    if (existing !== -1) {
        tabs[existing] = note;
    } else {
        const activeIndex = tabs.findIndex((tab) => tab.id === state.active);

        if (wantsNewTab || activeIndex === -1) {
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
    const tabs = state.tabs.filter((tab) => tab.id !== id);

    if (state.active !== id) {
        return { state: writeTabs(vaultId, { ...state, tabs }), next: null };
    }

    const next = tabs[Math.min(index, tabs.length - 1)]?.id ?? null;

    return { state: writeTabs(vaultId, { tabs, active: next }), next };
}
