import { Link } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { useState } from 'react';
import type { DragEvent } from 'react';
import { cn } from '@/lib/utils';
import { show } from '@/routes/notes';

export type BoardRow = {
    id: number;
    title: string;
    path: string;
    properties: Record<string, unknown>;
};

type Props = {
    vaultId: number;
    rows: BoardRow[];
    groupBy: string;
    /** Other properties to show on each card. */
    columns: string[];
    onMove: (row: BoardRow, value: string | null) => void;
};

const NO_VALUE = '\u0000none';
const DRAG_TYPE = 'application/x-kite-row';

function display(value: unknown): string {
    if (Array.isArray(value)) {
        return value.map(String).join(', ');
    }

    return typeof value === 'string' ||
        typeof value === 'number' ||
        typeof value === 'boolean'
        ? String(value)
        : JSON.stringify(value);
}

function columnOf(row: BoardRow, groupBy: string): string {
    const value = row.properties[groupBy];

    return value === undefined || value === null || value === ''
        ? NO_VALUE
        : display(value);
}

/**
 * Notes as cards in a column per value of one property; dragging a card to another column
 * changes that property in the note.
 */
export function ViewBoard({ vaultId, rows, groupBy, columns, onMove }: Props) {
    const [target, setTarget] = useState<string | null>(null);
    const values = [...new Set(rows.map((row) => columnOf(row, groupBy)))].sort(
        (a, b) =>
            a === NO_VALUE
                ? 1
                : b === NO_VALUE
                  ? -1
                  : a.localeCompare(b, undefined, { numeric: true }),
    );

    if (!values.includes(NO_VALUE)) {
        values.push(NO_VALUE);
    }

    const drop = (event: DragEvent, value: string) => {
        event.preventDefault();
        setTarget(null);

        const row = rows.find(
            (candidate) =>
                String(candidate.id) === event.dataTransfer.getData(DRAG_TYPE),
        );

        if (row && columnOf(row, groupBy) !== value) {
            onMove(row, value === NO_VALUE ? null : value);
        }
    };

    return (
        <div className="flex h-full items-start gap-4" data-test="view-board">
            {values.map((value) => {
                const cards = rows.filter(
                    (row) => columnOf(row, groupBy) === value,
                );

                return (
                    <section
                        key={value}
                        onDragOver={(event) => {
                            if (event.dataTransfer.types.includes(DRAG_TYPE)) {
                                event.preventDefault();
                                setTarget(value);
                            }
                        }}
                        onDragLeave={() => setTarget(null)}
                        onDrop={(event) => drop(event, value)}
                        className={cn(
                            'flex max-h-full w-72 shrink-0 flex-col rounded-xl bg-muted/50 p-2 transition-colors',
                            target === value &&
                                'bg-kite-accent-soft ring-2 ring-primary/30',
                        )}
                        data-test="board-column"
                        data-value={value === NO_VALUE ? '' : value}
                    >
                        <h3 className="flex items-center gap-2 px-1.5 pt-0.5 pb-2 text-[12.5px] font-semibold">
                            <span
                                className={cn(
                                    'truncate',
                                    value === NO_VALUE &&
                                        'text-muted-foreground',
                                )}
                            >
                                {value === NO_VALUE ? `No ${groupBy}` : value}
                            </span>
                            <span className="rounded-full bg-background px-1.5 text-[11px] font-medium text-muted-foreground tabular-nums">
                                {cards.length}
                            </span>
                        </h3>
                        <div className="min-h-12 space-y-2 overflow-y-auto">
                            {cards.map((row) => (
                                <article
                                    key={row.id}
                                    draggable
                                    onDragStart={(event) => {
                                        event.dataTransfer.setData(
                                            DRAG_TYPE,
                                            String(row.id),
                                        );
                                        event.dataTransfer.effectAllowed =
                                            'move';
                                    }}
                                    className="cursor-grab rounded-lg border bg-card p-3 shadow-kite-sm transition-shadow hover:shadow-kite-lg active:cursor-grabbing"
                                    data-test="board-card"
                                    data-title={row.title}
                                >
                                    <Link
                                        href={show({
                                            vault: vaultId,
                                            note: row.id,
                                        })}
                                        className="flex items-start gap-2 text-[13.5px] font-medium hover:text-primary"
                                        draggable={false}
                                    >
                                        <FileText className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                        <span className="min-w-0 break-words">
                                            {row.title}
                                        </span>
                                    </Link>
                                    {columns.some(
                                        (key) =>
                                            key !== groupBy &&
                                            row.properties[key] !== undefined &&
                                            row.properties[key] !== null,
                                    ) && (
                                        <dl className="mt-2 grid grid-cols-[auto_1fr] gap-x-2 gap-y-0.5 text-xs">
                                            {columns
                                                .filter(
                                                    (key) =>
                                                        key !== groupBy &&
                                                        row.properties[key] !==
                                                            undefined &&
                                                        row.properties[key] !==
                                                            null,
                                                )
                                                .map((key) => (
                                                    <div
                                                        key={key}
                                                        className="contents"
                                                    >
                                                        <dt className="text-muted-foreground">
                                                            {key}
                                                        </dt>
                                                        <dd className="truncate">
                                                            {display(
                                                                row.properties[
                                                                    key
                                                                ],
                                                            )}
                                                        </dd>
                                                    </div>
                                                ))}
                                        </dl>
                                    )}
                                </article>
                            ))}
                        </div>
                    </section>
                );
            })}
        </div>
    );
}
