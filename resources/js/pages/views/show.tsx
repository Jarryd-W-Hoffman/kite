import { Head, Link, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, FileText, Settings2, Table2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { CommandPalette } from '@/components/kite/command-palette';
import { EmptyState } from '@/components/kite/empty-state';
import { TYPES, typeOf } from '@/components/kite/properties-editor';
import type { PropertyType } from '@/components/kite/properties-editor';
import { TableViewDialog } from '@/components/kite/table-view-dialog';
import type { TableViewSettings } from '@/components/kite/table-view-dialog';
import { WorkspaceSidebar } from '@/components/kite/workspace-sidebar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { useVaultSync } from '@/hooks/use-vault-sync';
import { cn } from '@/lib/utils';
import { update as updateFrontmatter } from '@/routes/note-frontmatter';
import { show } from '@/routes/notes';
import { update as updateView } from '@/routes/table-views';
import type { WorkspaceProps } from '@/types';

type Row = {
    id: number;
    title: string;
    path: string;
    modified_at: number;
    properties: Record<string, unknown>;
};

/** What a table cell can write back to a note's frontmatter. */
type CellValue = string | number | boolean | string[] | null;

type Props = WorkspaceProps & {
    view: TableViewSettings & { id: number };
    rows: Row[];
    keys: string[];
};

/** The column's type, taken from the first note that has a value for it. */
function columnType(rows: Row[], key: string): PropertyType {
    const sample = rows.find(
        (row) =>
            row.properties[key] !== undefined && row.properties[key] !== null,
    );

    return sample ? typeOf(sample.properties[key]) : 'text';
}

function compare(a: unknown, b: unknown): number {
    if (a === undefined || a === null || a === '') {
        return b === undefined || b === null || b === '' ? 0 : 1;
    }

    if (b === undefined || b === null || b === '') {
        return -1;
    }

    if (typeof a === 'number' && typeof b === 'number') {
        return a - b;
    }

    const text = (value: unknown) =>
        typeof value === 'string' ? value : JSON.stringify(value);

    return text(a).localeCompare(text(b), undefined, { numeric: true });
}

export default function ViewShow({
    vault,
    vaults,
    tree,
    folders,
    colour_groups: colourGroups,
    tags,
    indexing,
    watching,
    view,
    rows,
    keys,
}: Props) {
    const [paletteOpen, setPaletteOpen] = useState(false);
    const [editing, setEditing] = useState(
        () => new URLSearchParams(window.location.search).get('edit') === '1',
    );

    useVaultSync(
        vault,
        () =>
            router.reload({
                only: ['rows', 'keys', 'tree', 'folders', 'tags'],
            }),
        watching,
    );

    const saveView = (changes: Partial<TableViewSettings>) =>
        router.put(
            updateView({ vault: vault.id, tableView: view.id }).url,
            { ...view, ...changes },
            { preserveScroll: true, preserveState: true },
        );

    const sorted = useMemo(() => {
        const key = view.sort_key;

        if (key === null) {
            return rows;
        }

        const value = (row: Row) =>
            key === 'title' ? row.title : row.properties[key];
        const ordered = [...rows].sort((a, b) => compare(value(a), value(b)));

        return view.sort_desc ? ordered.reverse() : ordered;
    }, [rows, view.sort_key, view.sort_desc]);

    const sortBy = (key: string) =>
        saveView(
            view.sort_key === key
                ? view.sort_desc
                    ? { sort_key: null, sort_desc: false }
                    : { sort_desc: true }
                : { sort_key: key, sort_desc: false },
        );

    const setCell = (row: Row, key: string, value: CellValue) =>
        router.put(
            updateFrontmatter({ vault: vault.id, note: row.id }).url,
            { key, value },
            {
                only: ['rows', 'keys'],
                preserveScroll: true,
                preserveState: true,
            },
        );

    const columns = view.columns.map((key) => ({
        key,
        type: columnType(rows, key),
    }));

    return (
        <>
            <Head title={`${view.name} · ${vault.name}`} />

            <div className="flex h-svh overflow-hidden bg-background">
                <WorkspaceSidebar
                    vault={vault}
                    vaults={vaults}
                    tree={tree}
                    folders={folders}
                    colourGroups={colourGroups}
                    tags={tags}
                    activeNoteId={null}
                    activeViewId={view.id}
                    indexing={indexing}
                    onSearch={() => setPaletteOpen(true)}
                />

                <main className="flex min-w-0 flex-1 flex-col">
                    <header className="kite-drag flex h-12 shrink-0 items-center gap-3 border-b border-border/70 px-4">
                        <h1 className="flex min-w-0 items-center gap-2 text-[13.5px] font-semibold">
                            <Table2 className="size-4 shrink-0 text-primary" />
                            <span className="truncate">{view.name}</span>
                        </h1>
                        <span className="rounded-full bg-muted px-2 py-0.5 text-[11px] text-muted-foreground tabular-nums">
                            {rows.length} {rows.length === 1 ? 'note' : 'notes'}
                        </span>
                        {view.query !== '' && (
                            <code className="hidden truncate rounded-md bg-muted px-1.5 py-0.5 text-[11px] text-muted-foreground md:block">
                                {view.query}
                            </code>
                        )}
                        <Button
                            variant="outline"
                            size="sm"
                            className="kite-no-drag ml-auto h-8"
                            onClick={() => setEditing(true)}
                            data-test="edit-view"
                        >
                            <Settings2 />
                            Edit view
                        </Button>
                    </header>

                    <div className="min-h-0 flex-1 overflow-auto p-6">
                        {rows.length === 0 ? (
                            <EmptyState
                                icon={Table2}
                                title="No notes match this view"
                                description="Edit the view's query, e.g. folder:Projects or status:active."
                                className="h-full"
                            />
                        ) : (
                            <div className="inline-block min-w-full overflow-hidden rounded-xl border bg-card align-top shadow-kite-sm">
                                <table
                                    className="w-full border-collapse text-[13.5px]"
                                    data-test="view-table"
                                >
                                    <thead className="sticky top-0 z-10 bg-muted/70 backdrop-blur">
                                        <tr>
                                            <HeaderCell
                                                label="Name"
                                                icon={FileText}
                                                sort={
                                                    view.sort_key === 'title'
                                                        ? view.sort_desc
                                                            ? 'desc'
                                                            : 'asc'
                                                        : null
                                                }
                                                onClick={() => sortBy('title')}
                                                className="min-w-56"
                                            />
                                            {columns.map((column) => (
                                                <HeaderCell
                                                    key={column.key}
                                                    label={column.key}
                                                    icon={
                                                        TYPES[column.type].icon
                                                    }
                                                    sort={
                                                        view.sort_key ===
                                                        column.key
                                                            ? view.sort_desc
                                                                ? 'desc'
                                                                : 'asc'
                                                            : null
                                                    }
                                                    onClick={() =>
                                                        sortBy(column.key)
                                                    }
                                                />
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {sorted.map((row) => (
                                            <tr
                                                key={row.id}
                                                className="border-t transition-colors hover:bg-accent/40"
                                                data-test="view-row"
                                            >
                                                <td className="px-3 py-1.5">
                                                    <Link
                                                        href={show({
                                                            vault: vault.id,
                                                            note: row.id,
                                                        })}
                                                        className="flex items-center gap-2 font-medium hover:text-primary"
                                                        title={row.path}
                                                    >
                                                        <FileText className="size-4 shrink-0 text-muted-foreground" />
                                                        <span className="truncate">
                                                            {row.title}
                                                        </span>
                                                    </Link>
                                                </td>
                                                {columns.map((column) => (
                                                    <td
                                                        key={column.key}
                                                        className="border-l px-1.5 py-1"
                                                    >
                                                        <Cell
                                                            name={`${row.title} ${column.key}`}
                                                            type={column.type}
                                                            value={
                                                                row.properties[
                                                                    column.key
                                                                ]
                                                            }
                                                            onChange={(value) =>
                                                                setCell(
                                                                    row,
                                                                    column.key,
                                                                    value,
                                                                )
                                                            }
                                                        />
                                                    </td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </main>
            </div>

            <TableViewDialog
                vault={vault}
                view={view}
                keys={keys}
                open={editing}
                onOpenChange={setEditing}
                onSave={(settings) => {
                    saveView(settings);
                    setEditing(false);
                }}
            />

            <CommandPalette
                vault={vault}
                open={paletteOpen}
                onOpenChange={setPaletteOpen}
            />
        </>
    );
}

function HeaderCell({
    label,
    icon: Icon,
    sort,
    onClick,
    className,
}: {
    label: string;
    icon: typeof FileText;
    sort: 'asc' | 'desc' | null;
    onClick: () => void;
    className?: string;
}) {
    return (
        <th
            className={cn(
                'border-l px-1.5 py-1 text-left first:border-l-0',
                className,
            )}
            aria-sort={
                sort === 'asc'
                    ? 'ascending'
                    : sort === 'desc'
                      ? 'descending'
                      : undefined
            }
        >
            <button
                type="button"
                onClick={onClick}
                className="flex w-full items-center gap-1.5 rounded-md px-1.5 py-1 text-xs font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                data-test={`sort-${label}`}
            >
                <Icon className="size-3.5" />
                <span className="truncate">{label}</span>
                {sort === 'asc' && <ArrowUp className="ml-auto size-3.5" />}
                {sort === 'desc' && <ArrowDown className="ml-auto size-3.5" />}
            </button>
        </th>
    );
}

const cellInput =
    'h-7 w-full min-w-28 rounded-md border border-transparent bg-transparent px-1.5 outline-none transition-colors hover:bg-accent focus:border-border focus:bg-background';

function Cell({
    name,
    type,
    value,
    onChange,
}: {
    name: string;
    type: PropertyType;
    value: unknown;
    onChange: (value: CellValue) => void;
}) {
    if (type === 'checkbox') {
        return (
            <div className="flex h-7 items-center px-1.5">
                <Checkbox
                    aria-label={name}
                    checked={value === true}
                    onCheckedChange={(checked) => onChange(checked === true)}
                />
            </div>
        );
    }

    if (type === 'list' || Array.isArray(value)) {
        const items = Array.isArray(value) ? value.map(String) : [];

        return (
            <input
                key={items.join(',')}
                aria-label={name}
                defaultValue={items.join(', ')}
                placeholder="Empty"
                onBlur={(event) => {
                    const next = event.target.value
                        .split(',')
                        .map((item) => item.trim())
                        .filter(Boolean);

                    if (next.join(',') !== items.join(',')) {
                        onChange(next);
                    }
                }}
                onKeyDown={(event) =>
                    event.key === 'Enter' && event.currentTarget.blur()
                }
                className={cellInput}
            />
        );
    }

    if (type === 'other') {
        return (
            <code className="px-1.5 text-xs text-muted-foreground">
                {JSON.stringify(value)}
            </code>
        );
    }

    const scalar =
        value === undefined || value === null
            ? ''
            : String(value as string | number);

    return (
        <input
            key={scalar}
            aria-label={name}
            type={
                type === 'number' ? 'number' : type === 'date' ? 'date' : 'text'
            }
            defaultValue={scalar}
            placeholder="Empty"
            onBlur={(event) => {
                const next =
                    type === 'number'
                        ? event.target.value === ''
                            ? null
                            : Number(event.target.value)
                        : event.target.value;

                if (String(next ?? '') !== scalar) {
                    onChange(next);
                }
            }}
            onKeyDown={(event) =>
                event.key === 'Enter' && event.currentTarget.blur()
            }
            className={cn(cellInput, type === 'number' && 'tabular-nums')}
        />
    );
}
