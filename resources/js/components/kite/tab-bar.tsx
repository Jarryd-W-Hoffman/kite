import { Link, router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useAppCommands } from '@/hooks/use-app-commands';
import { closeTab, visitTab } from '@/lib/tabs';
import type { TabState } from '@/lib/tabs';
import { cn } from '@/lib/utils';
import { show } from '@/routes/notes';
import { show as showVault } from '@/routes/vaults';
import type { VaultSummary } from '@/types';

type Props = {
    vault: VaultSummary;
    note: { id: number; title: string };
};

export function TabBar({ vault, note }: Props) {
    // The parent keys this component by note, so each visit records the tab exactly once.
    const [state, setState] = useState<TabState>(() =>
        visitTab(vault.id, note),
    );

    const close = (id: number) => {
        const { state: next, next: target } = closeTab(vault.id, id);

        setState(next);

        if (id === note.id) {
            router.visit(
                target === null
                    ? showVault(vault.id)
                    : show({ vault: vault.id, note: target }),
            );
        }
    };

    useAppCommands({ 'close-tab': () => close(note.id) });

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

            const index = state.tabs.findIndex((tab) => tab.id === note.id);
            const step = event.shiftKey ? -1 : 1;
            const next =
                state.tabs[
                    (index + step + state.tabs.length) % state.tabs.length
                ];

            router.visit(show({ vault: vault.id, note: next.id }));
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [state, note.id, vault.id]);

    if (state.tabs.length < 2) {
        return null;
    }

    return (
        <div
            role="tablist"
            aria-label="Open notes"
            className="kite-drag flex h-10 shrink-0 items-end gap-1 overflow-x-auto border-b bg-sidebar px-2"
            data-test="tab-bar"
        >
            {state.tabs.map((tab) => (
                <div
                    key={tab.id}
                    role="tab"
                    aria-selected={tab.id === note.id}
                    className={cn(
                        'kite-no-drag group relative -mb-px flex h-8 max-w-52 min-w-28 items-center gap-1 rounded-t-lg border border-b-0 px-3 text-[12.5px] transition-colors',
                        tab.id === note.id
                            ? 'border-border bg-background font-medium text-foreground'
                            : 'border-transparent text-muted-foreground hover:bg-background/50 hover:text-foreground',
                    )}
                >
                    <Link
                        href={show({ vault: vault.id, note: tab.id })}
                        className="min-w-0 flex-1 truncate py-1"
                        title={tab.title}
                    >
                        {tab.title}
                    </Link>
                    <button
                        type="button"
                        onClick={() => close(tab.id)}
                        aria-label={`Close ${tab.title}`}
                        className="rounded p-0.5 opacity-60 group-hover:opacity-100 hover:bg-accent"
                    >
                        <X className="size-3" />
                    </button>
                </div>
            ))}
        </div>
    );
}
