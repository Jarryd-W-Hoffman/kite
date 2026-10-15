import { Link, router } from '@inertiajs/react';
import { ArrowUpRight, ChevronRight, Hash, Link2, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { GraphCanvas } from '@/components/kite/graph-canvas';
import type { GraphEdge, GraphNode } from '@/components/kite/graph-canvas';
import { PropertiesEditor } from '@/components/kite/properties-editor';
import type { Properties } from '@/components/kite/properties-editor';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { readStored, stringListCodec, writeStored } from '@/lib/storage';
import { cn } from '@/lib/utils';
import { store as linkMention } from '@/routes/note-mentions';
import { show, store } from '@/routes/notes';
import { show as showTag } from '@/routes/vault-tags';
import type {
    Backlink,
    NoteMeta,
    OutgoingLink,
    OutlineItem,
    UnlinkedMention,
    VaultSummary,
} from '@/types';

type Props = {
    vault: VaultSummary;
    note: NoteMeta;
    backlinks: Backlink[];
    outgoing: OutgoingLink[];
    mentions?: UnlinkedMention[];
    onSelectHeading?: (item: OutlineItem) => void;
    /** The editor's cursor line, to highlight the section being edited. */
    cursorLine?: number | null;
    /** Editable frontmatter; without onPropertiesChange the properties are listed read-only. */
    properties?: Properties;
    onPropertiesChange?: (properties: Properties) => void;
    children?: ReactNode;
};

function formatValue(value: unknown): string {
    if (Array.isArray(value)) {
        return value.map(formatValue).join(', ');
    }

    if (value !== null && typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

const COLLAPSED_KEY = 'kite:panel-collapsed';

function readCollapsed(): string[] {
    return readStored(COLLAPSED_KEY, stringListCodec, []);
}

/**
 * A panel section that can be folded away; which ones are folded is remembered across notes.
 */
function Section({
    title,
    count,
    children,
}: {
    title: string;
    count?: number;
    children: ReactNode;
}) {
    const [collapsed, setCollapsed] = useState(() =>
        readCollapsed().includes(title),
    );

    const toggle = () => {
        const next = !collapsed;
        const stored = readCollapsed().filter((item) => item !== title);

        writeStored(
            COLLAPSED_KEY,
            stringListCodec,
            next ? [...stored, title] : stored,
        );
        setCollapsed(next);
    };

    return (
        <section className="space-y-2">
            <h2>
                <button
                    type="button"
                    onClick={toggle}
                    aria-expanded={!collapsed}
                    data-test={`panel-section-${title.toLowerCase().replace(/\s+/g, '-')}`}
                    className="flex w-full items-center gap-2 rounded text-xs font-medium tracking-wide text-muted-foreground uppercase hover:text-foreground"
                >
                    <ChevronRight
                        className={cn(
                            'size-3 shrink-0 transition-transform',
                            !collapsed && 'rotate-90',
                        )}
                    />
                    {title}
                    {count !== undefined && (
                        <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] tabular-nums">
                            {count}
                        </span>
                    )}
                </button>
            </h2>
            {!collapsed && children}
        </section>
    );
}

/**
 * The line's text with wiki links to this note emphasised.
 */
function LinkContext({ text }: { text: string }) {
    const parts = text.split(/(!?\[\[[^[\]\n]*\]\])/);

    return (
        <>
            {parts.map((part, index) =>
                index % 2 === 1 ? (
                    <mark
                        key={index}
                        className="rounded bg-transparent font-medium text-[var(--kite-link)]"
                    >
                        {part}
                    </mark>
                ) : (
                    part
                ),
            )}
        </>
    );
}

type BacklinkGroup = {
    id: number;
    title: string;
    path: string;
    lines: Backlink[];
};

function groupBacklinks(backlinks: Backlink[]): BacklinkGroup[] {
    const groups = new Map<number, BacklinkGroup>();

    for (const backlink of backlinks) {
        const group = groups.get(backlink.id) ?? {
            id: backlink.id,
            title: backlink.title,
            path: backlink.path,
            lines: [],
        };

        group.lines.push(backlink);
        groups.set(backlink.id, group);
    }

    return [...groups.values()];
}

/** Show a filter box once there are this many linking notes. */
const BACKLINK_FILTER_THRESHOLD = 6;

function Backlinks({
    vault,
    backlinks,
}: {
    vault: VaultSummary;
    backlinks: Backlink[];
}) {
    const [filter, setFilter] = useState('');
    const groups = groupBacklinks(backlinks);
    const needle = filter.trim().toLowerCase();
    const visible =
        needle === ''
            ? groups
            : groups.filter(
                  (group) =>
                      group.title.toLowerCase().includes(needle) ||
                      group.lines.some((line) =>
                          line.context.toLowerCase().includes(needle),
                      ),
              );

    if (groups.length === 0) {
        return <p className="text-muted-foreground">No notes link here yet.</p>;
    }

    return (
        <div className="space-y-2">
            {groups.length >= BACKLINK_FILTER_THRESHOLD && (
                <input
                    value={filter}
                    onChange={(event) => setFilter(event.target.value)}
                    placeholder="Filter backlinks…"
                    aria-label="Filter backlinks"
                    className="h-7 w-full rounded-md border bg-background px-2 text-xs outline-none focus-visible:ring-2 focus-visible:ring-ring"
                />
            )}
            <ul className="space-y-1" data-test="backlinks">
                {visible.map((group) => (
                    <li key={group.id} data-test="backlink-group">
                        <Link
                            href={show({ vault: vault.id, note: group.id })}
                            className="block truncate rounded-md px-2 py-1 font-medium hover:bg-accent"
                            title={group.path}
                        >
                            {group.title}
                            {group.lines.length > 1 && (
                                <span className="ml-1.5 text-xs font-normal text-muted-foreground tabular-nums">
                                    {group.lines.length}
                                </span>
                            )}
                        </Link>
                        <ul className="ml-2 border-l pl-2">
                            {group.lines
                                .filter((line) => line.context !== '')
                                .map((line) => (
                                    <li key={line.line}>
                                        <Link
                                            href={`${show({ vault: vault.id, note: group.id }).url}#L${line.line}`}
                                            className="line-clamp-2 rounded px-1.5 py-1 text-xs text-muted-foreground hover:bg-accent hover:text-foreground"
                                            title={`Line ${line.line}`}
                                            data-test="backlink-line"
                                        >
                                            <LinkContext text={line.context} />
                                        </Link>
                                    </li>
                                ))}
                        </ul>
                    </li>
                ))}
                {visible.length === 0 && (
                    <li className="px-2 py-1 text-xs text-muted-foreground">
                        No backlinks match.
                    </li>
                )}
            </ul>
        </div>
    );
}

export function NotePanel({
    vault,
    note,
    backlinks,
    outgoing,
    mentions,
    onSelectHeading,
    cursorLine = null,
    properties,
    onPropertiesChange,
    children,
}: Props) {
    const currentHeading =
        cursorLine === null
            ? -1
            : note.outline.findLastIndex((item) => item.line <= cursorLine);

    const localGraph = useMemo(() => {
        const nodes = new Map<string, GraphNode & { noteId: number | null }>();
        const center = `n${note.id}`;

        nodes.set(center, {
            id: center,
            noteId: note.id,
            title: note.title,
            folder: '',
            degree: 0,
            ghost: false,
        });

        for (const link of outgoing) {
            const id =
                link.note_id === null ? `g${link.target}` : `n${link.note_id}`;

            nodes.set(id, {
                id,
                noteId: link.note_id,
                title: link.title ?? link.target,
                folder: '',
                degree: 1,
                ghost: link.note_id === null,
            });
        }

        for (const backlink of backlinks) {
            nodes.set(`n${backlink.id}`, {
                id: `n${backlink.id}`,
                noteId: backlink.id,
                title: backlink.title,
                folder: '',
                degree: 1,
                ghost: false,
            });
        }

        const edges: GraphEdge[] = [...nodes.keys()]
            .filter((id) => id !== center)
            .map((id) => ({ source: center, target: id }));

        nodes.get(center)!.degree = edges.length;

        return { nodes: [...nodes.values()], edges };
    }, [note.id, note.title, outgoing, backlinks]);

    const readOnly = Object.entries(note.frontmatter).filter(
        ([key]) => !['title', 'tags', 'tag'].includes(key),
    );

    return (
        <div className="space-y-6 p-4 text-sm">
            {children}

            {localGraph.edges.length > 0 && (
                <Section title="Local graph">
                    <div className="h-48 overflow-hidden rounded-lg border bg-background">
                        <GraphCanvas
                            compact
                            nodes={localGraph.nodes}
                            edges={localGraph.edges}
                            activeId={`n${note.id}`}
                            onOpen={(node) => {
                                const match = localGraph.nodes.find(
                                    (candidate) => candidate.id === node.id,
                                );

                                if (match?.noteId) {
                                    router.visit(
                                        show({
                                            vault: vault.id,
                                            note: match.noteId,
                                        }),
                                    );
                                } else {
                                    router.post(store(vault.id).url, {
                                        title: node.title,
                                    });
                                }
                            }}
                        />
                    </div>
                </Section>
            )}

            {note.outline.length > 0 && (
                <Section title="Outline">
                    <nav aria-label="Outline">
                        <ul className="space-y-0.5">
                            {note.outline.map((item, index) => (
                                <li key={`${item.slug}:${item.line}`}>
                                    <button
                                        type="button"
                                        onClick={() => onSelectHeading?.(item)}
                                        data-test={`outline-${item.slug}`}
                                        aria-current={
                                            index === currentHeading
                                                ? 'location'
                                                : undefined
                                        }
                                        className={cn(
                                            'block w-full truncate rounded-md px-2 py-1 text-left hover:bg-accent',
                                            item.level > 1 &&
                                                'text-muted-foreground',
                                            index === currentHeading &&
                                                'bg-accent font-medium text-foreground',
                                        )}
                                        style={{
                                            paddingLeft: `${(item.level - 1) * 12 + 8}px`,
                                        }}
                                    >
                                        {item.text}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </nav>
                </Section>
            )}

            <Section
                title="Backlinks"
                count={new Set(backlinks.map((backlink) => backlink.id)).size}
            >
                <Backlinks vault={vault} backlinks={backlinks} />
            </Section>

            <Section title="Unlinked mentions" count={mentions?.length}>
                {mentions === undefined ? (
                    <div className="space-y-2" aria-busy="true">
                        <Skeleton className="h-4 w-3/4 animate-pulse" />
                        <Skeleton className="h-3 w-full animate-pulse" />
                        <Skeleton className="h-4 w-1/2 animate-pulse" />
                    </div>
                ) : mentions.length === 0 ? (
                    <p className="text-muted-foreground">
                        No unlinked mentions.
                    </p>
                ) : (
                    <ul className="space-y-1" data-test="unlinked-mentions">
                        {mentions.map((mention) => (
                            <li
                                key={`${mention.id}:${mention.line}`}
                                className="group rounded-md px-2 py-1.5 hover:bg-accent"
                            >
                                <div className="flex items-center gap-2">
                                    <Link
                                        href={show({
                                            vault: vault.id,
                                            note: mention.id,
                                        })}
                                        className="min-w-0 flex-1 truncate font-medium"
                                    >
                                        {mention.title}
                                    </Link>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="h-6 px-2 text-xs"
                                        onClick={() =>
                                            router.post(
                                                linkMention({
                                                    vault: vault.id,
                                                    note: note.id,
                                                }).url,
                                                {
                                                    source_id: mention.id,
                                                    line: mention.line,
                                                },
                                                {
                                                    preserveScroll: true,
                                                    preserveState: true,
                                                },
                                            )
                                        }
                                    >
                                        Link
                                    </Button>
                                </div>
                                <span className="mt-0.5 line-clamp-2 block text-xs text-muted-foreground">
                                    {mention.context}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </Section>

            <Section title="Links" count={outgoing.length}>
                {outgoing.length === 0 ? (
                    <p className="text-muted-foreground">
                        Type [[ to link another note.
                    </p>
                ) : (
                    <ul className="space-y-0.5">
                        {outgoing.map((link) =>
                            link.note_id === null ? (
                                <li key={link.target}>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            router.post(store(vault.id).url, {
                                                title: link.target,
                                            })
                                        }
                                        className="flex w-full items-center gap-2 rounded-md px-2 py-1 text-left text-muted-foreground hover:bg-accent"
                                        title="Create this note"
                                    >
                                        <Plus className="size-3.5 shrink-0" />
                                        <span className="truncate border-b border-dashed">
                                            {link.target}
                                        </span>
                                    </button>
                                </li>
                            ) : (
                                <li key={link.target}>
                                    <Link
                                        href={show({
                                            vault: vault.id,
                                            note: link.note_id,
                                        })}
                                        className="flex items-center gap-2 rounded-md px-2 py-1 hover:bg-accent"
                                    >
                                        <Link2 className="size-3.5 shrink-0 text-muted-foreground" />
                                        <span className="truncate">
                                            {link.title ?? link.target}
                                        </span>
                                        <ArrowUpRight className="ml-auto size-3 shrink-0 text-muted-foreground" />
                                    </Link>
                                </li>
                            ),
                        )}
                    </ul>
                )}
            </Section>

            {note.tags.length > 0 && (
                <Section title="Tags">
                    <div className="flex flex-wrap gap-1">
                        {note.tags.map((tag) => (
                            <Link
                                key={tag}
                                href={showTag({ vault: vault.id, tag })}
                                className="inline-flex items-center gap-0.5 rounded-md bg-kite-accent-soft px-1.5 py-0.5 text-xs font-medium text-primary transition-colors hover:bg-primary hover:text-primary-foreground"
                            >
                                <Hash className="size-3" />
                                {tag}
                            </Link>
                        ))}
                    </div>
                </Section>
            )}

            {onPropertiesChange ? (
                <Section title="Properties">
                    <PropertiesEditor
                        properties={properties ?? {}}
                        onChange={onPropertiesChange}
                    />
                </Section>
            ) : (
                readOnly.length > 0 && (
                    <Section title="Properties">
                        <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs">
                            {readOnly.map(([key, value]) => (
                                <div key={key} className="contents">
                                    <dt className="text-muted-foreground">
                                        {key}
                                    </dt>
                                    <dd className="truncate">
                                        {formatValue(value)}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </Section>
                )
            )}

            <p className="text-xs text-muted-foreground tabular-nums">
                {note.word_count.toLocaleString()} words · {note.path}
            </p>
        </div>
    );
}
