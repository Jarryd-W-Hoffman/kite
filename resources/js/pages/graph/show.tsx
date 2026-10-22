import { Head, router } from '@inertiajs/react';
import { EyeOff, FilePlus2, Filter, Network, Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ColourGroupEditor } from '@/components/kite/colour-group-editor';
import {
    ColourGroupLegend,
    UNGROUPED,
} from '@/components/kite/colour-group-legend';
import type { LegendKey } from '@/components/kite/colour-group-legend';
import { CommandPalette } from '@/components/kite/command-palette';
import { EmptyState } from '@/components/kite/empty-state';
import { GraphCanvas } from '@/components/kite/graph-canvas';
import type { GraphEdge, GraphNode } from '@/components/kite/graph-canvas';
import {
    WorkspaceSidebar,
    createNote,
} from '@/components/kite/workspace-sidebar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useVaultSync } from '@/hooks/use-vault-sync';
import { cn } from '@/lib/utils';
import { show, store } from '@/routes/notes';
import type { WorkspaceProps } from '@/types';

type Props = WorkspaceProps & {
    /** Only the notes this matches are drawn, e.g. `folder:Work -tag:archived`. */
    filter: string;
    filter_error: string | null;
    graph: {
        nodes: (GraphNode & { note_id: number | null; path: string | null })[];
        edges: GraphEdge[];
    };
};

export default function GraphShow({
    vault,
    vaults,
    tree,
    folders,
    tags,
    graph,
    filter: initialFilter,
    filter_error: filterError,
    colour_groups: colourGroups,
    indexing,
    watching,
}: Props) {
    const [query, setQuery] = useState('');
    const [showGhosts, setShowGhosts] = useState(true);
    const [showOrphans, setShowOrphans] = useState(true);
    const [colourBy, setColourBy] = useState<'folder' | 'tag' | 'group'>(() =>
        colourGroups.length > 0 ? 'group' : 'folder',
    );
    const [hiddenGroups, setHiddenGroups] = useState<Set<LegendKey>>(
        () => new Set(),
    );
    const [editingGroups, setEditingGroups] = useState(false);
    const [tagFilter, setTagFilter] = useState('');
    const [paletteOpen, setPaletteOpen] = useState(false);
    const [filter, setFilter] = useState(initialFilter);
    const filterTimer = useRef<number | undefined>(undefined);

    // The server draws only the matching notes; a pause in typing applies the filter.
    const changeFilter = (value: string) => {
        setFilter(value);
        window.clearTimeout(filterTimer.current);
        filterTimer.current = window.setTimeout(
            () =>
                router.reload({
                    only: ['graph', 'filter', 'filter_error'],
                    data: { filter: value.trim() === '' ? undefined : value },
                    replace: true,
                }),
            350,
        );
    };

    useEffect(() => () => window.clearTimeout(filterTimer.current), []);

    useVaultSync(
        vault,
        () =>
            router.reload({
                only: ['graph', 'tree', 'folders', 'tags', 'colour_groups'],
            }),
        watching,
    );

    const legendKey = (node: { group?: number | null }): LegendKey =>
        node.group ?? UNGROUPED;

    const { nodes, edges, groupCounts } = useMemo(() => {
        const visible = graph.nodes.filter(
            (node) =>
                (showGhosts || !node.ghost) &&
                (showOrphans || node.degree > 0) &&
                (colourBy !== 'group' || !hiddenGroups.has(legendKey(node))),
        );
        const counts = new Map<LegendKey, number>();

        for (const node of graph.nodes) {
            if (!node.ghost) {
                counts.set(
                    legendKey(node),
                    (counts.get(legendKey(node)) ?? 0) + 1,
                );
            }
        }
        const ids = new Set(visible.map((node) => node.id));

        return {
            nodes: visible,
            edges: graph.edges.filter(
                (edge) => ids.has(edge.source) && ids.has(edge.target),
            ),
            groupCounts: counts,
        };
    }, [graph, showGhosts, showOrphans, colourBy, hiddenGroups]);

    const toggleGroup = (key: LegendKey) =>
        setHiddenGroups((current) => {
            const next = new Set(current);

            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }

            return next;
        });

    const noteCount = graph.nodes.filter((node) => !node.ghost).length;

    const resetFilters = () => {
        setShowGhosts(true);
        setShowOrphans(true);
        setHiddenGroups(new Set());
    };

    const open = (node: GraphNode) => {
        const match = graph.nodes.find((candidate) => candidate.id === node.id);

        if (match?.note_id) {
            router.visit(show({ vault: vault.id, note: match.note_id }));
        } else {
            router.post(store(vault.id).url, { title: node.title });
        }
    };

    return (
        <>
            <Head title={`Graph · ${vault.name}`} />

            <div className="flex h-svh overflow-hidden bg-background">
                <WorkspaceSidebar
                    vault={vault}
                    vaults={vaults}
                    tree={tree}
                    folders={folders}
                    colourGroups={colourGroups}
                    tags={tags}
                    activeNoteId={null}
                    indexing={indexing}
                    graphActive
                    onSearch={() => setPaletteOpen(true)}
                />

                <main className="relative flex min-w-0 flex-1 flex-col">
                    <header className="kite-drag flex min-h-12 shrink-0 flex-wrap items-center gap-x-3 gap-y-2 border-b border-border/70 px-4 py-2">
                        <h1 className="flex items-center gap-2 text-[13.5px] font-semibold">
                            <Network className="size-4 text-primary" />
                            Graph
                        </h1>
                        <span className="shrink-0 rounded-full bg-muted px-2 py-0.5 text-[11px] whitespace-nowrap text-muted-foreground tabular-nums">
                            {noteCount} notes · {graph.edges.length} links
                        </span>

                        <div className="kite-no-drag ml-auto flex flex-wrap items-center justify-end gap-x-3 gap-y-2">
                            <label className="flex items-center gap-2 text-[13px]">
                                <span className="text-muted-foreground">
                                    Colour
                                </span>
                                <select
                                    value={colourBy}
                                    onChange={(event) =>
                                        setColourBy(
                                            event.target.value === 'tag' ||
                                                event.target.value === 'group'
                                                ? event.target.value
                                                : 'folder',
                                        )
                                    }
                                    aria-label="Colour nodes by"
                                    data-test="graph-colour"
                                    className="kite-select"
                                >
                                    <option value="folder">by folder</option>
                                    <option value="tag">by tag</option>
                                    <option value="group">by group</option>
                                </select>
                            </label>
                            <select
                                value={tagFilter}
                                onChange={(event) =>
                                    setTagFilter(event.target.value)
                                }
                                aria-label="Highlight a tag"
                                data-test="graph-tag"
                                className="kite-select max-w-40"
                            >
                                <option value="">All tags</option>
                                {tags.map((tag) => (
                                    <option key={tag.name} value={tag.name}>
                                        #{tag.name} ({tag.count})
                                    </option>
                                ))}
                            </select>
                            <div
                                className="flex items-center gap-2 text-[13px]"
                                title="Show links to notes that don't exist yet, as hollow dots"
                                data-test="graph-unresolved"
                            >
                                <Checkbox
                                    id="show-ghosts"
                                    checked={showGhosts}
                                    onCheckedChange={(value) =>
                                        setShowGhosts(value === true)
                                    }
                                />
                                <Label htmlFor="show-ghosts">Unresolved</Label>
                            </div>
                            <div
                                className="flex items-center gap-2 text-[13px]"
                                title="Show notes with no links in or out"
                                data-test="graph-orphans"
                            >
                                <Checkbox
                                    id="show-orphans"
                                    checked={showOrphans}
                                    onCheckedChange={(value) =>
                                        setShowOrphans(value === true)
                                    }
                                />
                                <Label htmlFor="show-orphans">Orphans</Label>
                            </div>
                            <label
                                className={cn(
                                    'flex h-8 items-center gap-2 rounded-md border bg-background px-2.5 shadow-kite-sm focus-within:ring-2 focus-within:ring-ring',
                                    filterError && 'border-destructive',
                                )}
                                title="Filter with tag:, folder:, path:, title:, any property (status:done) and - to leave out"
                            >
                                <Filter
                                    className="size-3.5 text-muted-foreground"
                                    aria-hidden
                                />
                                <input
                                    value={filter}
                                    onChange={(event) =>
                                        changeFilter(event.target.value)
                                    }
                                    placeholder="Filter: folder:Work -tag:old"
                                    aria-label="Filter notes"
                                    aria-invalid={filterError !== null}
                                    aria-describedby={
                                        filterError
                                            ? 'graph-filter-error'
                                            : undefined
                                    }
                                    data-test="graph-filter"
                                    className="w-40 bg-transparent text-sm outline-none xl:w-52"
                                />
                            </label>
                            <label className="flex h-8 items-center gap-2 rounded-md border bg-background px-2.5 shadow-kite-sm focus-within:ring-2 focus-within:ring-ring">
                                <Search className="size-3.5 text-muted-foreground" />
                                <input
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                    placeholder="Highlight notes…"
                                    aria-label="Highlight notes"
                                    className="w-32 bg-transparent text-sm outline-none xl:w-40"
                                />
                            </label>
                        </div>
                    </header>

                    {filterError && (
                        <p
                            id="graph-filter-error"
                            role="alert"
                            className="border-b border-l-4 border-l-destructive bg-destructive/10 px-4 py-1.5 text-xs text-foreground"
                            data-test="graph-filter-error"
                        >
                            {filterError} Showing every note.
                        </p>
                    )}

                    <div className="kite-dot-grid relative min-h-0 flex-1">
                        {noteCount === 0 &&
                        initialFilter !== '' &&
                        !filterError ? (
                            <EmptyState
                                icon={Filter}
                                title="No notes match this filter"
                                description="Try fewer terms, or clear the filter to see every note."
                                className="h-full"
                            >
                                <Button
                                    variant="outline"
                                    onClick={() => changeFilter('')}
                                >
                                    Clear filter
                                </Button>
                            </EmptyState>
                        ) : noteCount === 0 ? (
                            <EmptyState
                                icon={Network}
                                title="No notes to graph yet"
                                description="Notes appear here as dots, and every [[wiki link]] between them becomes a line."
                                className="h-full"
                            >
                                <Button
                                    onClick={() => createNote(vault, 'Home')}
                                >
                                    <FilePlus2 />
                                    Create a note
                                </Button>
                            </EmptyState>
                        ) : nodes.length === 0 ? (
                            <EmptyState
                                icon={EyeOff}
                                title="Everything is filtered out"
                                description="The current filters hide every note in the graph."
                                className="h-full"
                            >
                                <Button
                                    variant="outline"
                                    onClick={resetFilters}
                                    data-test="reset-graph-filters"
                                >
                                    Show all notes
                                </Button>
                            </EmptyState>
                        ) : (
                            <GraphCanvas
                                nodes={nodes}
                                edges={edges}
                                highlight={query}
                                highlightTag={tagFilter}
                                colourBy={colourBy}
                                groups={colourGroups}
                                onOpen={open}
                            />
                        )}
                        {nodes.length > 0 && graph.edges.length === 0 && (
                            <p
                                className="pointer-events-none absolute inset-x-0 bottom-10 mx-auto w-fit rounded-full border bg-background/90 px-3 py-1.5 text-xs text-muted-foreground shadow-sm"
                                data-test="graph-no-links"
                            >
                                No links yet. Type [[ in a note to link it to
                                another.
                            </p>
                        )}
                        {colourBy === 'group' && (
                            <ColourGroupLegend
                                groups={colourGroups}
                                counts={groupCounts}
                                hidden={hiddenGroups}
                                onToggle={toggleGroup}
                                onEdit={() => setEditingGroups(true)}
                            />
                        )}
                        <p className="pointer-events-none absolute bottom-3 left-4 text-xs text-muted-foreground">
                            Scroll to zoom · drag to pan · double-click to fit
                        </p>
                    </div>
                </main>
            </div>

            <ColourGroupEditor
                vault={vault}
                groups={colourGroups}
                open={editingGroups}
                onOpenChange={setEditingGroups}
                reload={['graph', 'tree', 'colour_groups']}
            />

            <CommandPalette
                vault={vault}
                open={paletteOpen}
                onOpenChange={setPaletteOpen}
            />
        </>
    );
}
