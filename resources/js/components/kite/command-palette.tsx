import { router, usePage } from '@inertiajs/react';
import { FilePlus2, FileText, Search, SearchCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { runAppCommand } from '@/hooks/use-app-commands';
import { matchCommands } from '@/lib/commands';
import type { PaletteCommand } from '@/lib/commands';
import { useShortcuts } from '@/lib/key-bindings';
import { isAbortError, requestJson } from '@/lib/kite-http';
import { formatShortcut } from '@/lib/shortcuts';
import { cn } from '@/lib/utils';
import { show, store } from '@/routes/notes';
import { store as storeSearch } from '@/routes/saved-searches';
import { index as search } from '@/routes/vault-search';
import type { SearchResult, VaultSummary } from '@/types';

type Props = {
    vault: VaultSummary;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Offer the commands that act on the open note. */
    noteOpen?: boolean;
};

/** Filter terms the search understands, matching the server's (`tag:work`, `-folder:Archive`). */
const FILTER = /(?<!\S)-?[\p{L}_][\p{L}\p{N}_-]*:(?!\/\/)(?:"[^"]*"|\S+)/gu;

type Item =
    | { type: 'note'; result: SearchResult }
    | { type: 'create'; title: string }
    | { type: 'save'; query: string }
    | { type: 'command'; command: PaletteCommand };

export function CommandPalette({
    vault,
    open,
    onOpenChange,
    noteOpen = false,
}: Props) {
    const { kite } = usePage().props;
    const shortcutFor = useShortcuts();
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchResult[]>([]);
    const [resultsQuery, setResultsQuery] = useState<string | null>(null);
    const [activeIndex, setActiveIndex] = useState(0);
    const listRef = useRef<HTMLUListElement>(null);

    const commandMode = query.startsWith('>');
    const filters = query.match(FILTER) ?? [];

    useEffect(() => {
        if (!open || commandMode) {
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(() => {
            requestJson<{ results: SearchResult[] }>(
                search(vault.id, { query: { q: query } }),
                undefined,
                controller.signal,
            )
                .then((response) => {
                    setResults(response.results);
                    setResultsQuery(query);
                    setActiveIndex(0);
                })
                .catch((error: unknown) => {
                    if (!isAbortError(error)) {
                        setResults([]);
                    }
                });
        }, 60);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [open, query, vault.id, commandMode]);

    const trimmed = query.trim();
    const commandItems: Item[] = commandMode
        ? matchCommands(query.slice(1), {
              noteOpen,
              desktop: kite.native,
          }).map((command) => ({ type: 'command' as const, command }))
        : [];
    const items: Item[] = commandMode
        ? commandItems
        : [
              ...results.map((result) => ({ type: 'note' as const, result })),
              ...(trimmed !== '' &&
              resultsQuery === query &&
              !results.some(
                  (result) =>
                      result.title.toLowerCase() === trimmed.toLowerCase(),
              )
                  ? [{ type: 'create' as const, title: trimmed }]
                  : []),
              // Keep what was typed, filters and all, as a sidebar search.
              ...(trimmed !== '' && resultsQuery === query
                  ? [{ type: 'save' as const, query: trimmed }]
                  : []),
          ];

    const close = () => {
        onOpenChange(false);
        setQuery('');
        setResultsQuery(null);
    };

    const choose = (item: Item | undefined, inSplit = false) => {
        if (!item) {
            return;
        }

        close();

        if (item.type === 'command') {
            // Let the palette close before the command opens anything of its own.
            window.setTimeout(() => runAppCommand(item.command.id), 0);
        } else if (item.type === 'note' && inSplit) {
            window.dispatchEvent(
                new CustomEvent('kite:open-split', { detail: item.result.id }),
            );
        } else if (item.type === 'note') {
            router.visit(show({ vault: vault.id, note: item.result.id }));
        } else if (item.type === 'save') {
            router.post(storeSearch(vault.id).url, { query: item.query });
        } else {
            router.post(store(vault.id).url, { title: item.title });
        }
    };

    const onKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();

            const delta = event.key === 'ArrowDown' ? 1 : -1;
            const next =
                (activeIndex + delta + items.length) %
                Math.max(items.length, 1);

            setActiveIndex(next);
            listRef.current
                ?.querySelector(`[data-index="${next}"]`)
                ?.scrollIntoView({ block: 'nearest' });
        }

        if (event.key === 'Enter') {
            event.preventDefault();

            if (
                (event.metaKey || event.ctrlKey) &&
                trimmed !== '' &&
                !commandMode
            ) {
                choose({ type: 'create', title: trimmed });
            } else {
                choose(items[activeIndex], event.shiftKey);
            }
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => (value ? onOpenChange(true) : close())}
        >
            <DialogContent className="top-[16%] translate-y-0 gap-0 overflow-hidden rounded-2xl p-0 sm:max-w-2xl [&>button]:hidden">
                <DialogTitle className="sr-only">Search notes</DialogTitle>
                <DialogDescription className="sr-only">
                    Type to search every note in {vault.name}. Press Enter to
                    open, Shift+Enter to open beside the current note, or Ctrl
                    or Cmd+Enter to create a note.
                </DialogDescription>
                <div className="flex items-center gap-3 border-b px-5">
                    <Search className="size-[18px] shrink-0 text-muted-foreground" />
                    <input
                        autoFocus
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={onKeyDown}
                        placeholder={
                            commandMode
                                ? 'Run a command…'
                                : 'Search notes, filter with tag: folder: status:… or > for commands'
                        }
                        aria-label="Search notes"
                        data-test="command-palette-input"
                        role="combobox"
                        aria-expanded={items.length > 0}
                        aria-controls="kite-palette-results"
                        aria-activedescendant={
                            items.length > 0
                                ? `kite-palette-item-${activeIndex}`
                                : undefined
                        }
                        className="h-14 w-full bg-transparent text-[15px] outline-none placeholder:text-muted-foreground/70"
                    />
                    <kbd className="rounded-md border bg-muted/60 px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
                        esc
                    </kbd>
                </div>

                {!commandMode && filters.length > 0 && (
                    <div
                        className="flex flex-wrap items-center gap-1.5 border-b px-5 py-2 text-xs text-muted-foreground"
                        data-test="palette-filters"
                    >
                        Filtered by
                        {filters.map((filter) => (
                            <span
                                key={filter}
                                className={cn(
                                    'rounded-md px-1.5 py-0.5 font-mono',
                                    filter.startsWith('-')
                                        ? 'bg-destructive/10 text-destructive-foreground'
                                        : 'bg-kite-accent-soft text-primary',
                                )}
                            >
                                {filter}
                            </span>
                        ))}
                    </div>
                )}
                <ul
                    id="kite-palette-results"
                    ref={listRef}
                    role="listbox"
                    className="max-h-[52vh] overflow-y-auto p-2"
                >
                    {items.length === 0 &&
                        (commandMode || resultsQuery === query) && (
                            <li className="px-3 py-10 text-center text-sm text-muted-foreground">
                                {commandMode
                                    ? 'No commands match.'
                                    : trimmed === ''
                                      ? 'No notes yet. Type a title to create one.'
                                      : 'No notes found.'}
                            </li>
                        )}
                    {items.map((item, index) => (
                        <li
                            key={
                                item.type === 'note'
                                    ? item.result.id
                                    : item.type === 'command'
                                      ? `command-${item.command.id}`
                                      : item.type === 'save'
                                        ? `save-${item.query}`
                                        : `create-${item.title}`
                            }
                            id={`kite-palette-item-${index}`}
                            data-index={index}
                            role="option"
                            aria-selected={index === activeIndex}
                            onMouseMove={() => setActiveIndex(index)}
                            onClick={() => choose(item)}
                            className={cn(
                                'flex cursor-pointer items-start gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors',
                                index === activeIndex && 'bg-accent',
                            )}
                        >
                            {item.type === 'note' ? (
                                <>
                                    <span
                                        className={cn(
                                            'flex size-7 shrink-0 items-center justify-center rounded-md border bg-background text-muted-foreground',
                                            index === activeIndex &&
                                                'border-primary/30 text-primary',
                                        )}
                                    >
                                        <FileText className="size-4" />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-baseline gap-2">
                                            <span className="truncate font-medium">
                                                {item.result.title}
                                            </span>
                                            <span className="truncate text-xs text-muted-foreground">
                                                {item.result.path}
                                            </span>
                                        </div>
                                        {item.result.snippet !== '' && (
                                            <p
                                                className="kite-snippet mt-0.5 line-clamp-2 text-xs text-muted-foreground"
                                                dangerouslySetInnerHTML={{
                                                    __html: item.result.snippet,
                                                }}
                                            />
                                        )}
                                    </div>
                                </>
                            ) : item.type === 'command' ? (
                                <>
                                    <span
                                        className={cn(
                                            'flex size-7 shrink-0 items-center justify-center rounded-md border bg-background text-muted-foreground',
                                            index === activeIndex &&
                                                'border-primary/30 text-primary',
                                        )}
                                    >
                                        <item.command.icon className="size-4" />
                                    </span>
                                    <span
                                        className="flex-1 self-center"
                                        data-test="palette-command"
                                    >
                                        {item.command.label}
                                    </span>
                                    {shortcutFor(
                                        item.command.id,
                                        item.command.shortcut,
                                    ) && (
                                        <kbd className="self-center rounded border bg-background px-1.5 py-0.5 font-sans text-[10px] text-muted-foreground">
                                            {formatShortcut(
                                                shortcutFor(
                                                    item.command.id,
                                                    item.command.shortcut,
                                                ) ?? '',
                                                kite.platform,
                                            )}
                                        </kbd>
                                    )}
                                </>
                            ) : item.type === 'save' ? (
                                <>
                                    <span className="flex size-7 shrink-0 items-center justify-center rounded-md border bg-background text-muted-foreground">
                                        <SearchCheck className="size-4" />
                                    </span>
                                    <span
                                        className="self-center"
                                        data-test="palette-save-search"
                                    >
                                        Save search{' '}
                                        <span className="font-medium">
                                            “{item.query}”
                                        </span>
                                    </span>
                                </>
                            ) : (
                                <>
                                    <span className="flex size-7 shrink-0 items-center justify-center rounded-md bg-kite-accent-soft text-primary">
                                        <FilePlus2 className="size-4" />
                                    </span>
                                    <span className="self-center">
                                        Create note{' '}
                                        <span className="font-medium">
                                            “{item.title}”
                                        </span>
                                    </span>
                                </>
                            )}
                        </li>
                    ))}
                </ul>
                <div className="flex items-center gap-4 border-t bg-muted/40 px-4 py-2 text-[11px] text-muted-foreground">
                    <Hint keys="↑↓">navigate</Hint>
                    {commandMode ? (
                        <Hint keys="↵">run</Hint>
                    ) : (
                        <>
                            <Hint keys="↵">open</Hint>
                            <Hint keys="⇧↵">open beside</Hint>
                            <Hint
                                keys={
                                    kite.platform === 'darwin' ? '⌘↵' : 'Ctrl+↵'
                                }
                            >
                                create
                            </Hint>
                            <Hint keys=">">commands</Hint>
                        </>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}

function Hint({ keys, children }: { keys: string; children: string }) {
    return (
        <span className="flex items-center gap-1.5">
            <kbd className="rounded border bg-background px-1 py-px font-sans text-[10px] font-medium">
                {keys}
            </kbd>
            {children}
        </span>
    );
}
