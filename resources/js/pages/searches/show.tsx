import { Head, Link, router } from '@inertiajs/react';
import { FileText, SearchCheck, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { CommandPalette } from '@/components/kite/command-palette';
import { EmptyState } from '@/components/kite/empty-state';
import { WorkspaceSidebar } from '@/components/kite/workspace-sidebar';
import { Button } from '@/components/ui/button';
import { useVaultSync } from '@/hooks/use-vault-sync';
import { show as showNote } from '@/routes/notes';
import { destroy, update } from '@/routes/saved-searches';
import type { SearchResult, WorkspaceProps } from '@/types';

type Props = WorkspaceProps & {
    search: { id: number; name: string; query: string };
    results: SearchResult[];
};

const SAVE_DELAY_MS = 400;

export default function SavedSearchShow(props: Props) {
    const {
        vault,
        vaults,
        tree,
        folders,
        tags,
        indexing,
        watching,
        search,
        results,
    } = props;
    const [paletteOpen, setPaletteOpen] = useState(false);
    const [name, setName] = useState(search.name);
    const [query, setQuery] = useState(search.query);
    const timer = useRef<number | undefined>(undefined);

    useVaultSync(
        vault,
        () => router.reload({ only: ['results', 'tree', 'folders', 'tags'] }),
        watching,
    );

    // A different search opened in the same page starts from its own name and text.
    const [shown, setShown] = useState(search.id);

    if (shown !== search.id) {
        setShown(search.id);
        setName(search.name);
        setQuery(search.query);
    }

    const save = (next: { name: string; query: string }) => {
        window.clearTimeout(timer.current);
        timer.current = window.setTimeout(() => {
            if (next.name.trim() === '') {
                return;
            }

            router.put(
                update({ vault: vault.id, savedSearch: search.id }).url,
                next,
                {
                    only: ['search', 'results', 'saved_searches'],
                    preserveState: true,
                    preserveScroll: true,
                },
            );
        }, SAVE_DELAY_MS);
    };

    useEffect(() => () => window.clearTimeout(timer.current), []);

    return (
        <>
            <Head title={`${search.name} · ${vault.name}`} />

            <div className="flex h-svh overflow-hidden bg-background">
                <WorkspaceSidebar
                    vault={vault}
                    vaults={vaults}
                    tree={tree}
                    folders={folders}
                    colourGroups={props.colour_groups}
                    tags={tags}
                    activeNoteId={null}
                    activeSearchId={search.id}
                    indexing={indexing}
                    onSearch={() => setPaletteOpen(true)}
                />

                <main className="flex min-w-0 flex-1 flex-col">
                    <header className="kite-drag flex h-12 shrink-0 items-center gap-2 border-b border-border/70 px-4 text-[13px] text-muted-foreground">
                        <SearchCheck className="size-3.5" aria-hidden />
                        Saved search
                        <Button
                            variant="ghost"
                            size="icon"
                            className="kite-no-drag ml-auto size-8"
                            aria-label="Delete this search"
                            data-test="delete-search"
                            onClick={() =>
                                router.delete(
                                    destroy({
                                        vault: vault.id,
                                        savedSearch: search.id,
                                    }).url,
                                )
                            }
                        >
                            <Trash2 />
                        </Button>
                    </header>

                    <div className="min-h-0 flex-1 overflow-y-auto">
                        <div className="mx-auto max-w-3xl px-8 pt-12 pb-24">
                            <label
                                htmlFor="saved-search-name"
                                className="sr-only"
                            >
                                Search name
                            </label>
                            <input
                                id="saved-search-name"
                                value={name}
                                maxLength={80}
                                data-test="search-name"
                                className="w-full bg-transparent text-3xl font-bold tracking-tight outline-none placeholder:text-muted-foreground/50"
                                placeholder="Name this search"
                                onChange={(event) => {
                                    setName(event.target.value);
                                    save({ name: event.target.value, query });
                                }}
                            />

                            <label
                                htmlFor="saved-search-query"
                                className="mt-6 mb-1.5 block text-xs font-medium text-muted-foreground"
                            >
                                Find notes with
                            </label>
                            <input
                                id="saved-search-query"
                                value={query}
                                maxLength={500}
                                data-test="search-query"
                                autoFocus={search.query === ''}
                                aria-describedby="saved-search-help"
                                className="h-10 w-full rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-ring"
                                placeholder="Words to find, and filters like tag:project folder:Work -tag:archived status:done"
                                onChange={(event) => {
                                    setQuery(event.target.value);
                                    save({ name, query: event.target.value });
                                }}
                            />
                            <p
                                id="saved-search-help"
                                className="mt-1.5 text-xs text-muted-foreground"
                            >
                                Filters: <code>tag:</code>, <code>folder:</code>
                                , <code>path:</code>, <code>title:</code>, any
                                property such as <code>status:done</code>, and{' '}
                                <code>-</code> to leave matches out.
                            </p>

                            <p
                                className="mt-8 mb-3 text-sm text-muted-foreground tabular-nums"
                                aria-live="polite"
                                data-test="search-count"
                            >
                                {query.trim() === ''
                                    ? ''
                                    : `${results.length} ${results.length === 1 ? 'note' : 'notes'}`}
                            </p>

                            {query.trim() === '' ? (
                                <EmptyState
                                    icon={SearchCheck}
                                    title="What should this search find?"
                                    description="Type words or filters above. The results stay up to date as your notes change."
                                />
                            ) : results.length === 0 ? (
                                <EmptyState
                                    icon={SearchCheck}
                                    title="No notes match"
                                    description="Try fewer words or filters."
                                />
                            ) : (
                                <ul
                                    className="grid gap-2"
                                    data-test="search-results"
                                >
                                    {results.map((result) => (
                                        <li key={result.id}>
                                            <Link
                                                href={showNote({
                                                    vault: vault.id,
                                                    note: result.id,
                                                })}
                                                className="group/card flex items-start gap-3 rounded-xl border bg-card p-4 shadow-kite-sm transition-all hover:-translate-y-px hover:border-primary/30 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            >
                                                <span
                                                    className="flex size-8 shrink-0 items-center justify-center rounded-lg border bg-background text-muted-foreground group-hover/card:text-primary"
                                                    aria-hidden
                                                >
                                                    <FileText className="size-4" />
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <div className="truncate font-semibold">
                                                        {result.title}
                                                    </div>
                                                    <div className="truncate text-xs text-muted-foreground">
                                                        {result.path}
                                                    </div>
                                                    {result.snippet !== '' && (
                                                        <p
                                                            className="kite-snippet mt-1.5 line-clamp-2 text-sm text-muted-foreground"
                                                            dangerouslySetInnerHTML={{
                                                                __html: result.snippet,
                                                            }}
                                                        />
                                                    )}
                                                </div>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>
                </main>
            </div>

            <CommandPalette
                vault={vault}
                open={paletteOpen}
                onOpenChange={setPaletteOpen}
            />
        </>
    );
}
