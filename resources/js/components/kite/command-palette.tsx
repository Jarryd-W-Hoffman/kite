import { router, usePage } from '@inertiajs/react';
import { FilePlus2, FileText, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { isAbortError, requestJson } from '@/lib/kite-http';
import { cn } from '@/lib/utils';
import { show, store } from '@/routes/notes';
import { index as search } from '@/routes/vault-search';
import type { SearchResult, VaultSummary } from '@/types';

type Props = {
    vault: VaultSummary;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

type Item =
    | { type: 'note'; result: SearchResult }
    | { type: 'create'; title: string };

export function CommandPalette({ vault, open, onOpenChange }: Props) {
    const { kite } = usePage().props;
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchResult[]>([]);
    const [resultsQuery, setResultsQuery] = useState<string | null>(null);
    const [activeIndex, setActiveIndex] = useState(0);
    const listRef = useRef<HTMLUListElement>(null);

    useEffect(() => {
        if (!open) {
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
    }, [open, query, vault.id]);

    const trimmed = query.trim();
    const items: Item[] = [
        ...results.map((result) => ({ type: 'note' as const, result })),
        ...(trimmed !== '' &&
        resultsQuery === query &&
        !results.some(
            (result) => result.title.toLowerCase() === trimmed.toLowerCase(),
        )
            ? [{ type: 'create' as const, title: trimmed }]
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

        if (item.type === 'note' && inSplit) {
            window.dispatchEvent(
                new CustomEvent('kite:open-split', { detail: item.result.id }),
            );
        } else if (item.type === 'note') {
            router.visit(show({ vault: vault.id, note: item.result.id }));
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

            if ((event.metaKey || event.ctrlKey) && trimmed !== '') {
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
                        placeholder="Search or create a note…"
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

                <ul
                    id="kite-palette-results"
                    ref={listRef}
                    role="listbox"
                    className="max-h-[52vh] overflow-y-auto p-2"
                >
                    {items.length === 0 && resultsQuery === query && (
                        <li className="px-3 py-10 text-center text-sm text-muted-foreground">
                            {trimmed === ''
                                ? 'No notes yet. Type a title to create one.'
                                : 'No notes found.'}
                        </li>
                    )}
                    {items.map((item, index) => (
                        <li
                            key={
                                item.type === 'note'
                                    ? item.result.id
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
                    <Hint keys="↵">open</Hint>
                    <Hint keys="⇧↵">open beside</Hint>
                    <Hint keys={kite.platform === 'darwin' ? '⌘↵' : 'Ctrl+↵'}>
                        create
                    </Hint>
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
