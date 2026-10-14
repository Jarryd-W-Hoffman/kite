import { Palette } from 'lucide-react';
import { groupColourVariable } from '@/lib/colour-groups';
import type { ColourGroup } from '@/lib/colour-groups';
import { cn } from '@/lib/utils';

/** Legend key for notes that match no group. */
export const UNGROUPED = 'ungrouped';

export type LegendKey = number | typeof UNGROUPED;

type Props = {
    groups: ColourGroup[];
    /** Visible notes per group id, plus UNGROUPED. */
    counts: Map<LegendKey, number>;
    hidden: Set<LegendKey>;
    onToggle: (key: LegendKey) => void;
    onEdit: () => void;
};

/**
 * The key for "colour by group": one row per group in match order, click to show or hide it.
 */
export function ColourGroupLegend({
    groups,
    counts,
    hidden,
    onToggle,
    onEdit,
}: Props) {
    const rows: { key: LegendKey; name: string; colour: string }[] = [
        ...groups.map((group) => ({
            key: group.id,
            name: group.name,
            colour: groupColourVariable(group.colour),
        })),
        {
            key: UNGROUPED,
            name: 'Ungrouped',
            colour: 'var(--muted-foreground)',
        },
    ];

    return (
        <div
            className="kite-no-drag absolute top-3 left-4 z-10 w-56 rounded-lg border bg-background/90 p-2 text-sm shadow-sm backdrop-blur"
            data-test="colour-group-legend"
        >
            {groups.length === 0 ? (
                <p className="px-1.5 py-1 text-muted-foreground">
                    No colour groups yet.
                </p>
            ) : (
                <ul className="space-y-0.5">
                    {rows.map((row) => {
                        const isHidden = hidden.has(row.key);

                        return (
                            <li key={row.key}>
                                <button
                                    type="button"
                                    aria-pressed={!isHidden}
                                    title={isHidden ? 'Show' : 'Hide'}
                                    onClick={() => onToggle(row.key)}
                                    data-test="colour-group-legend-item"
                                    className={cn(
                                        'flex w-full items-center gap-2 rounded-md px-1.5 py-1 text-left hover:bg-accent',
                                        isHidden &&
                                            'text-muted-foreground line-through opacity-60',
                                    )}
                                >
                                    <span
                                        aria-hidden
                                        className="size-2.5 shrink-0 rounded-full"
                                        style={{ background: row.colour }}
                                    />
                                    <span className="min-w-0 flex-1 truncate">
                                        {row.name}
                                    </span>
                                    <span className="text-xs text-muted-foreground tabular-nums">
                                        {counts.get(row.key) ?? 0}
                                    </span>
                                </button>
                            </li>
                        );
                    })}
                </ul>
            )}
            <button
                type="button"
                onClick={onEdit}
                data-test="edit-colour-groups"
                className="mt-1 flex w-full items-center gap-2 rounded-md border-t px-1.5 pt-2 pb-1 text-muted-foreground hover:text-foreground"
            >
                <Palette className="size-3.5" />
                {groups.length === 0 ? 'Create a group…' : 'Edit groups…'}
            </button>
        </div>
    );
}
