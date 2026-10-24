import {
    ChevronLeft,
    ChevronRight,
    Columns2,
    Eye,
    LockKeyhole,
    MessageSquare,
    PanelRight,
    PencilLine,
    Star,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { SaveState } from '@/hooks/use-note-document';
import { cn } from '@/lib/utils';

type Props = {
    path: string;
    renaming: boolean;
    onRenamingChange: (renaming: boolean) => void;
    onRename: (path: string) => void;
    locked: boolean;
    saveState: SaveState;
    /** Whether the note is a daily note, with previous and next day buttons. */
    daily: boolean;
    onShiftDay: (days: number) => void;
    viewMode: string;
    onViewModeChange: (mode: string) => void;
    starred: boolean;
    onToggleStar: () => void;
    commentsOpen: boolean;
    openComments: number;
    onToggleComments: () => void;
    panelOpen: boolean;
    onTogglePanel: () => void;
    /** Focus mode fades the bar out until it's pointed at or tabbed into. */
    focus: boolean;
    /** The note's actions menu, at the end of the bar. */
    children: ReactNode;
};

/**
 * The bar above an open note: its path (click to rename), lock and save state, and the
 * buttons for the view, favourites, comments and details panel.
 */
export function NoteHeader({
    path,
    renaming,
    onRenamingChange,
    onRename,
    locked,
    saveState,
    daily,
    onShiftDay,
    viewMode,
    onViewModeChange,
    starred,
    onToggleStar,
    commentsOpen,
    openComments,
    onToggleComments,
    panelOpen,
    onTogglePanel,
    focus,
    children,
}: Props) {
    return (
        <header
            className={cn(
                'kite-drag flex h-12 shrink-0 items-center gap-2 border-b border-border/70 px-3',
                focus &&
                    'border-transparent opacity-0 transition-opacity duration-300 focus-within:opacity-100 hover:opacity-100',
            )}
        >
            <NotePathTitle
                path={path}
                renaming={renaming}
                onRenamingChange={onRenamingChange}
                onRename={onRename}
            />

            {locked && (
                <span
                    className="flex shrink-0 items-center gap-1 rounded-md bg-kite-accent-soft px-1.5 py-0.5 text-[11px] font-medium text-primary"
                    title="Encrypted on disk with the vault’s passphrase"
                    data-test="locked-badge"
                >
                    <LockKeyhole className="size-3" />
                    Locked
                </span>
            )}

            <SaveStatus state={saveState} />

            <div className="kite-no-drag ml-auto flex items-center gap-1">
                {daily && <DayNavigation onShift={onShiftDay} />}
                <ViewModeToggle value={viewMode} onChange={onViewModeChange} />

                <Button
                    variant="ghost"
                    size="icon"
                    className={cn(
                        'size-8',
                        starred && 'text-amber-500 hover:text-amber-500',
                    )}
                    aria-label={
                        starred ? 'Remove from favourites' : 'Add to favourites'
                    }
                    aria-pressed={starred}
                    data-test="star-note"
                    onClick={onToggleStar}
                >
                    <Star className={cn(starred && 'fill-current')} />
                </Button>

                {!locked && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-8 gap-1 px-2"
                        aria-label="Toggle comments"
                        aria-pressed={commentsOpen}
                        data-test="toggle-comments"
                        onClick={onToggleComments}
                    >
                        <MessageSquare />
                        {openComments > 0 && (
                            <span className="text-xs tabular-nums">
                                {openComments}
                            </span>
                        )}
                    </Button>
                )}

                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    aria-label="Toggle details panel"
                    aria-pressed={panelOpen}
                    onClick={onTogglePanel}
                >
                    <PanelRight />
                </Button>

                {children}
            </div>
        </header>
    );
}

/**
 * The note's folder and name; clicking it edits the path in place to rename or move the note.
 */
function NotePathTitle({
    path,
    renaming,
    onRenamingChange,
    onRename,
}: {
    path: string;
    renaming: boolean;
    onRenamingChange: (renaming: boolean) => void;
    onRename: (path: string) => void;
}) {
    if (renaming) {
        return (
            <input
                autoFocus
                defaultValue={path.replace(/\.md$/i, '')}
                aria-label="Note path"
                onBlur={(event) => onRename(event.target.value)}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                        onRename(event.currentTarget.value);
                    } else if (event.key === 'Escape') {
                        onRenamingChange(false);
                    }
                }}
                className="kite-no-drag min-w-0 flex-1 rounded-md border bg-background px-2 py-1 font-mono text-sm outline-none focus:ring-2 focus:ring-ring"
            />
        );
    }

    return (
        <button
            type="button"
            onClick={() => onRenamingChange(true)}
            title="Rename or move"
            className="kite-no-drag flex min-w-0 items-center gap-1 rounded-md px-2 py-1 text-[13.5px] transition-colors hover:bg-accent"
        >
            <span className="truncate text-muted-foreground">
                {path.includes('/')
                    ? `${path.slice(0, path.lastIndexOf('/'))} / `
                    : ''}
            </span>
            <span className="truncate font-medium">
                {path.split('/').pop()?.replace(/\.md$/i, '')}
            </span>
        </button>
    );
}

const SAVE_LABELS: Record<SaveState, string> = {
    saved: 'Saved',
    unsaved: 'Edited',
    saving: 'Saving…',
    error: 'Not saved',
    conflict: 'Changed on disk',
};

/**
 * Whether the note's latest changes are saved, announced to screen readers as it changes.
 */
function SaveStatus({ state }: { state: SaveState }) {
    return (
        <span
            className={cn(
                'flex shrink-0 items-center gap-1.5 text-xs text-muted-foreground',
                (state === 'error' || state === 'conflict') &&
                    'text-destructive-foreground',
            )}
            aria-live="polite"
        >
            <span
                aria-hidden
                className={cn(
                    'size-1.5 rounded-full bg-emerald-500/70 transition-colors',
                    state === 'unsaved' && 'bg-amber-500/80',
                    state === 'saving' && 'animate-pulse bg-primary/70',
                    (state === 'error' || state === 'conflict') &&
                        'bg-destructive',
                )}
            />
            {SAVE_LABELS[state]}
        </span>
    );
}

/**
 * Previous and next day, for a daily note.
 */
function DayNavigation({ onShift }: { onShift: (days: number) => void }) {
    return (
        <div className="mr-1 flex items-center">
            <Button
                variant="ghost"
                size="icon"
                className="size-8"
                aria-label="Previous day"
                data-test="previous-day"
                onClick={() => onShift(-1)}
            >
                <ChevronLeft />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                className="size-8"
                aria-label="Next day"
                data-test="next-day"
                onClick={() => onShift(1)}
            >
                <ChevronRight />
            </Button>
        </div>
    );
}

/**
 * Edit, split (editor beside preview) or preview.
 */
function ViewModeToggle({
    value,
    onChange,
}: {
    value: string;
    onChange: (mode: string) => void;
}) {
    return (
        <ToggleGroup
            type="single"
            size="sm"
            value={value}
            onValueChange={onChange}
            aria-label="View mode"
            className="mr-1 rounded-lg bg-muted p-0.5 [&_[data-slot=toggle-group-item]]:h-7 [&_[data-slot=toggle-group-item]]:rounded-md [&_[data-slot=toggle-group-item]]:px-2 [&_[data-slot=toggle-group-item]]:text-muted-foreground [&_[data-slot=toggle-group-item][data-state=on]]:bg-background [&_[data-slot=toggle-group-item][data-state=on]]:text-foreground [&_[data-slot=toggle-group-item][data-state=on]]:shadow-kite-sm"
        >
            <ToggleGroupItem value="edit" aria-label="Edit">
                <PencilLine />
            </ToggleGroupItem>
            <ToggleGroupItem value="split" aria-label="Split">
                <Columns2 />
            </ToggleGroupItem>
            <ToggleGroupItem value="preview" aria-label="Preview">
                <Eye />
            </ToggleGroupItem>
        </ToggleGroup>
    );
}
