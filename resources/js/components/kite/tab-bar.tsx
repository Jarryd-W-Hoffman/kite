import { Link, router } from '@inertiajs/react';
import { Pin, PinOff, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useAppCommands } from '@/hooks/use-app-commands';
import {
    closeTab,
    reconcileTabs,
    tabHref,
    tabKey,
    togglePin,
    visitTab,
} from '@/lib/tabs';
import type { Tab, TabState } from '@/lib/tabs';
import { cn } from '@/lib/utils';
import { show as showVault } from '@/routes/vaults';
import type { TreeNote, VaultSummary } from '@/types';

type Props = {
    vault: VaultSummary;
    /** What this page shows, as a tab. */
    current: Tab;
    /** The vault's notes, to drop tabs whose note is gone and keep titles current. */
    notes: TreeNote[];
};

export function TabBar({ vault, current, notes }: Props) {
    const currentKey = tabKey(current);

    // The parent keys this component by page, so each visit records the tab exactly once.
    const [state, setState] = useState<TabState>(() => {
        visitTab(vault.id, current);

        return reconcileTabs(vault.id, notes, tabKey(current));
    });

    const close = (closing: string) => {
        const { state: next, next: target } = closeTab(vault.id, closing);

        setState(next);

        if (closing === currentKey) {
            router.visit(
                target === null
                    ? showVault(vault.id)
                    : tabHref(vault.id, target),
            );
        }
    };

    useAppCommands({ 'close-tab': () => close(currentKey) });

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (
                !event.ctrlKey ||
                event.key !== 'Tab' ||
                state.tabs.length < 2
            ) {
                return;
            }

            event.preventDefault();

            const index = state.tabs.findIndex(
                (tab) => tabKey(tab) === currentKey,
            );
            const step = event.shiftKey ? -1 : 1;
            const next =
                state.tabs[
                    (index + step + state.tabs.length) % state.tabs.length
                ];

            router.visit(tabHref(vault.id, next));
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [state, currentKey, vault.id]);

    if (state.tabs.length < 2) {
        return null;
    }

    return (
        <div
            role="tablist"
            aria-label="Open notes"
            className="kite-drag flex h-10 shrink-0 items-end gap-1 overflow-x-auto overflow-y-hidden bg-sidebar px-2 shadow-[inset_0_-1px_0_var(--color-border)]"
            data-test="tab-bar"
        >
            {state.tabs.map((tab) => (
                <div
                    key={tabKey(tab)}
                    role="tab"
                    aria-selected={tabKey(tab) === currentKey}
                    className={cn(
                        'kite-no-drag group relative flex h-8 max-w-52 min-w-28 items-center gap-1 rounded-t-lg border border-b-0 px-3 text-[12.5px] transition-colors',
                        tab.pinned && 'max-w-40 min-w-0',
                        tabKey(tab) === currentKey
                            ? 'border-border bg-background font-medium text-foreground'
                            : 'border-transparent text-muted-foreground hover:bg-background/50 hover:text-foreground',
                    )}
                >
                    {tab.pinned && (
                        <Pin className="size-3 shrink-0 fill-current text-primary" />
                    )}
                    <Link
                        href={tabHref(vault.id, tab)}
                        className="min-w-0 flex-1 truncate py-1"
                        title={tab.title}
                    >
                        {tab.title}
                    </Link>
                    <button
                        type="button"
                        onClick={() =>
                            setState(togglePin(vault.id, tabKey(tab)))
                        }
                        aria-label={
                            tab.pinned
                                ? `Unpin ${tab.title}`
                                : `Pin ${tab.title}`
                        }
                        data-test="pin-tab"
                        className="rounded p-0.5 opacity-0 group-hover:opacity-60 hover:bg-accent hover:!opacity-100 focus-visible:opacity-100"
                    >
                        {tab.pinned ? (
                            <PinOff className="size-3" />
                        ) : (
                            <Pin className="size-3" />
                        )}
                    </button>
                    {!tab.pinned && (
                        <button
                            type="button"
                            onClick={() => close(tabKey(tab))}
                            aria-label={`Close ${tab.title}`}
                            className="rounded p-0.5 opacity-60 group-hover:opacity-100 hover:bg-accent"
                        >
                            <X className="size-3" />
                        </button>
                    )}
                </div>
            ))}
        </div>
    );
}
