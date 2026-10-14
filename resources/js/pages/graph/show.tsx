import { Head, router } from '@inertiajs/react';
import { EyeOff, FilePlus2, Network, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
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
import { show, store } from '@/routes/notes';
import type { WorkspaceProps } from '@/types';

type Props = WorkspaceProps & {
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
                    <header className="kite-drag flex h-12 shrink-0 items-center gap-4 border-b px-4">
                        <h1 className="text-sm font-medium">Graph</h1>
                        <span className="text-xs text-muted-foreground tabular-nums">
                            {noteCount} notes · {graph.edges.length} links
                        </span>

                        <div className="kite-no-drag ml-auto flex items-center gap-4">
                            <label className="flex items-center gap-2 text-sm">
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
                                    className="rounded-md border bg-background px-2 py-1 text-sm"
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
                                className="max-w-40 rounded-md border bg-background px-2 py-1 text-sm"
                            >
                                <option value="">All tags</option>
                                {tags.map((tag) => (
                                    <option key={tag.name} value={tag.name}>
                                        #{tag.name} ({tag.count})
                                    </option>
                                ))}
                            </select>
                            <div className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    id="show-ghosts"
                                    checked={showGhosts}
                                    onCheckedChange={(value) =>
                                        setShowGhosts(value === true)
                                    }
                                />
                                <Label htmlFor="show-ghosts">Unresolved</Label>
                            </div>
                            <div className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    id="show-orphans"
                                    checked={showOrphans}
                                    onCheckedChange={(value) =>
                                        setShowOrphans(value === true)
                                    }
                                />
                                <Label htmlFor="show-orphans">Orphans</Label>
                            </div>
                            <label className="flex items-center gap-2 rounded-md border px-2 py-1">
                                <Search className="size-3.5 text-muted-foreground" />
                                <input
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                    placeholder="Highlight notes…"
                                    aria-label="Highlight notes"
                                    className="w-40 bg-transparent text-sm outline-none"
                                />
                            </label>
                        </div>
                    </header>

                    <div className="relative min-h-0 flex-1">
                        {noteCount === 0 ? (
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
