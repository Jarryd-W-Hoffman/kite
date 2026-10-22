import {
    ArrowRightLeft,
    Ban,
    FileText,
    Frame,
    Link2,
    Maximize,
    Minus,
    Plus,
    Redo2,
    Shapes,
    StickyNote,
    Trash2,
    Undo2,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    endsOf,
    LINE_STYLES,
    lineStyleOf,
    PRESET_COLORS,
    SHAPES,
    shapePaths,
} from '@/lib/canvas';
import type {
    CanvasEdge,
    CanvasEnds,
    CanvasShape,
    LineStyle,
} from '@/lib/canvas';
import { isAbortError, requestJson } from '@/lib/kite-http';
import { cn } from '@/lib/utils';
import { index as search } from '@/routes/vault-search';
import type { SearchResult, VaultSummary } from '@/types';

type Props = {
    vault: VaultSummary;
    readOnly: boolean;
    zoom: number;
    hasSelection: boolean;
    /** Text cards in the selection, which can take a shape. */
    selectedTextCards: number;
    /** Connections in the selection. */
    selectedEdges: CanvasEdge[];
    canUndo: boolean;
    canRedo: boolean;
    onAddText: () => void;
    onAddNote: (path: string) => void;
    onAddLink: (url: string) => void;
    onAddGroup: () => void;
    onAddShape: (shape: CanvasShape) => void;
    onShape: (shape: CanvasShape | null) => void;
    onEnds: (ends: CanvasEnds) => void;
    onReverse: () => void;
    onLineStyle: (style: LineStyle) => void;
    onColor: (color: string | null) => void;
    onDelete: () => void;
    onUndo: () => void;
    onRedo: () => void;
    onZoom: (factor: number) => void;
    onFit: () => void;
};

const ENDS: { ends: CanvasEnds; label: string }[] = [
    { ends: 'end', label: 'Arrow at one end' },
    { ends: 'both', label: 'Arrows at both ends' },
    { ends: 'none', label: 'No arrows' },
];

/**
 * The bar floating at the bottom of the canvas: add cards and shapes, colour, shape or delete
 * the selection, choose a connection's arrows and line, undo, and zoom.
 */
export function CanvasToolbar(props: Props) {
    const [picking, setPicking] = useState(false);
    const [linking, setLinking] = useState(false);
    // A shape chosen from the menu is added once the menu has closed and let go of focus, so the
    // new shape's text box keeps it.
    const chosenShape = useRef<CanvasShape | null>(null);
    const { readOnly, selectedEdges } = props;
    const sharedEnds = shared(selectedEdges.map(endsOf));
    const sharedLine = shared(selectedEdges.map(lineStyleOf));

    return (
        <>
            <div
                className="kite-no-drag absolute bottom-4 left-1/2 z-20 flex -translate-x-1/2 items-center gap-0.5 rounded-xl border bg-background/95 p-1 shadow-lg backdrop-blur"
                data-test="canvas-toolbar"
                onPointerDown={(event) => event.stopPropagation()}
                onDoubleClick={(event) => event.stopPropagation()}
            >
                {!readOnly && (
                    <>
                        <Tool
                            label="Add a card"
                            testId="canvas-add-text"
                            onClick={props.onAddText}
                        >
                            <StickyNote />
                        </Tool>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-8"
                                    aria-label="Add a shape"
                                    title="Add a shape"
                                    data-test="canvas-add-shape"
                                >
                                    <Shapes />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                side="top"
                                align="center"
                                className="kite-no-drag"
                                onCloseAutoFocus={(event) => {
                                    if (chosenShape.current !== null) {
                                        event.preventDefault();
                                        props.onAddShape(chosenShape.current);
                                        chosenShape.current = null;
                                    }
                                }}
                            >
                                <DropdownMenuLabel>
                                    Add a shape
                                </DropdownMenuLabel>
                                {SHAPES.map(({ shape, label }) => (
                                    <DropdownMenuItem
                                        key={shape}
                                        data-test={`canvas-add-shape-${shape}`}
                                        onSelect={() => {
                                            chosenShape.current = shape;
                                        }}
                                    >
                                        <ShapeIcon shape={shape} />
                                        {label}
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <Tool
                            label="Add a note"
                            testId="canvas-add-note"
                            onClick={() => setPicking(true)}
                        >
                            <FileText />
                        </Tool>
                        <Tool
                            label="Add a link"
                            testId="canvas-add-link"
                            onClick={() => setLinking(true)}
                        >
                            <Link2 />
                        </Tool>
                        <Tool
                            label={
                                props.hasSelection
                                    ? 'Group the selection'
                                    : 'Add a group'
                            }
                            testId="canvas-add-group"
                            onClick={props.onAddGroup}
                        >
                            <Frame />
                        </Tool>
                        <Divider />
                        {props.selectedTextCards > 0 && (
                            <>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="h-8 px-2 text-xs"
                                            title="Change the shape of the selected cards"
                                            data-test="canvas-shape"
                                        >
                                            Shape
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        side="top"
                                        align="center"
                                        className="kite-no-drag"
                                    >
                                        <DropdownMenuItem
                                            data-test="canvas-shape-none"
                                            onSelect={() => props.onShape(null)}
                                        >
                                            <StickyNote />
                                            Card
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        {SHAPES.map(({ shape, label }) => (
                                            <DropdownMenuItem
                                                key={shape}
                                                data-test={`canvas-shape-${shape}`}
                                                onSelect={() =>
                                                    props.onShape(shape)
                                                }
                                            >
                                                <ShapeIcon shape={shape} />
                                                {label}
                                            </DropdownMenuItem>
                                        ))}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                                <Divider />
                            </>
                        )}
                        {selectedEdges.length > 0 && (
                            <>
                                {ENDS.map(({ ends, label }) => (
                                    <Tool
                                        key={ends}
                                        label={label}
                                        testId={`canvas-ends-${ends}`}
                                        pressed={sharedEnds === ends}
                                        onClick={() => props.onEnds(ends)}
                                    >
                                        <EndsIcon ends={ends} />
                                    </Tool>
                                ))}
                                <Tool
                                    label="Reverse direction"
                                    testId="canvas-reverse"
                                    onClick={props.onReverse}
                                >
                                    <ArrowRightLeft />
                                </Tool>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-8"
                                            aria-label="Line style"
                                            title="Line style"
                                            data-test="canvas-line-style"
                                        >
                                            <LineIcon
                                                style={sharedLine ?? 'solid'}
                                            />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        side="top"
                                        align="center"
                                        className="kite-no-drag"
                                    >
                                        <DropdownMenuRadioGroup
                                            value={sharedLine ?? ''}
                                            onValueChange={(value) =>
                                                props.onLineStyle(
                                                    value as LineStyle,
                                                )
                                            }
                                        >
                                            {LINE_STYLES.map(
                                                ({ style, label }) => (
                                                    <DropdownMenuRadioItem
                                                        key={style}
                                                        value={style}
                                                        data-test={`canvas-line-${style}`}
                                                    >
                                                        <LineIcon
                                                            style={style}
                                                        />
                                                        {label}
                                                    </DropdownMenuRadioItem>
                                                ),
                                            )}
                                        </DropdownMenuRadioGroup>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                                <Divider />
                            </>
                        )}
                        {props.hasSelection && (
                            <>
                                {Object.entries(PRESET_COLORS).map(
                                    ([color, value]) => (
                                        <button
                                            key={color}
                                            type="button"
                                            aria-label={`Colour ${color}`}
                                            data-test={`canvas-color-${color}`}
                                            className="mx-0.5 size-5 rounded-full border border-black/10 transition-transform hover:scale-110"
                                            style={{ backgroundColor: value }}
                                            onClick={() => props.onColor(color)}
                                        />
                                    ),
                                )}
                                <Tool
                                    label="No colour"
                                    onClick={() => props.onColor(null)}
                                >
                                    <Ban />
                                </Tool>
                                <Tool
                                    label="Delete"
                                    testId="canvas-delete"
                                    onClick={props.onDelete}
                                >
                                    <Trash2 />
                                </Tool>
                                <Divider />
                            </>
                        )}
                        <Tool
                            label="Undo"
                            testId="canvas-undo"
                            disabled={!props.canUndo}
                            onClick={props.onUndo}
                        >
                            <Undo2 />
                        </Tool>
                        <Tool
                            label="Redo"
                            testId="canvas-redo"
                            disabled={!props.canRedo}
                            onClick={props.onRedo}
                        >
                            <Redo2 />
                        </Tool>
                        <Divider />
                    </>
                )}
                <Tool label="Zoom out" onClick={() => props.onZoom(1 / 1.25)}>
                    <Minus />
                </Tool>
                <span
                    className="w-11 text-center text-xs text-muted-foreground tabular-nums"
                    data-test="canvas-zoom"
                >
                    {Math.round(props.zoom * 100)}%
                </span>
                <Tool label="Zoom in" onClick={() => props.onZoom(1.25)}>
                    <Plus />
                </Tool>
                <Tool
                    label="Show everything"
                    testId="canvas-fit"
                    onClick={props.onFit}
                >
                    <Maximize />
                </Tool>
            </div>

            <NotePicker
                vault={props.vault}
                open={picking}
                onOpenChange={setPicking}
                onPick={(path) => {
                    setPicking(false);
                    props.onAddNote(path);
                }}
            />
            <LinkDialog
                open={linking}
                onOpenChange={setLinking}
                onAdd={(url) => {
                    setLinking(false);
                    props.onAddLink(url);
                }}
            />
        </>
    );
}

/** The value every item has, or null when they differ (or there are none). */
function shared<T>(values: T[]): T | null {
    return values.length > 0 && values.every((value) => value === values[0])
        ? values[0]
        : null;
}

/** A small outline of a shape, for menus. */
function ShapeIcon({ shape }: { shape: CanvasShape }) {
    const size = shape === 'circle' ? [14, 14] : [18, 12];
    const { outline, details } = shapePaths(shape, size[0], size[1]);

    return (
        <svg
            aria-hidden
            viewBox={`-1 -1 ${size[0] + 2} ${size[1] + 2}`}
            className="h-4 w-6"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.25}
        >
            <path d={outline} />
            {details && <path d={details} />}
        </svg>
    );
}

/** A line with arrowheads where a connection has them. */
function EndsIcon({ ends }: { ends: CanvasEnds }) {
    return (
        <svg
            aria-hidden
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={2}
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <path d="M3 12h18" />
            {ends !== 'none' && <path d="m16 7 5 5-5 5" />}
            {ends === 'both' && <path d="m8 7-5 5 5 5" />}
        </svg>
    );
}

/** The line styles' dashes, scaled down to fit an icon. */
const ICON_DASHES: Record<LineStyle, string | undefined> = {
    solid: undefined,
    'long-dashed': '6 4',
    'short-dashed': '3 3',
    dotted: '0.5 4.5',
};

/** A line drawn in a line style. */
function LineIcon({ style }: { style: LineStyle }) {
    return (
        <svg
            aria-hidden
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={2}
            strokeLinecap="round"
        >
            <path d="M2 12h20" strokeDasharray={ICON_DASHES[style]} />
        </svg>
    );
}

function Tool({
    label,
    testId,
    disabled,
    pressed,
    onClick,
    children,
}: {
    label: string;
    testId?: string;
    disabled?: boolean;
    /** For a choice that's on or off: whether it's chosen. */
    pressed?: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            className={cn(
                'size-8',
                pressed && 'bg-accent text-accent-foreground',
            )}
            aria-label={label}
            aria-pressed={pressed}
            title={label}
            data-test={testId}
            disabled={disabled}
            onClick={onClick}
        >
            {children}
        </Button>
    );
}

function Divider() {
    return <span className="mx-1 h-5 w-px bg-border" />;
}

/**
 * Find a note to put on the canvas, by name or text.
 */
function NotePicker({
    vault,
    open,
    onOpenChange,
    onPick,
}: {
    vault: VaultSummary;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onPick: (path: string) => void;
}) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchResult[]>([]);
    const [active, setActive] = useState(0);

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
                    setActive(0);
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

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setQuery('');
                onOpenChange(next);
            }}
        >
            <DialogContent
                className="kite-no-drag gap-3 p-3 sm:max-w-lg"
                data-test="canvas-note-picker"
            >
                <DialogHeader className="sr-only">
                    <DialogTitle>Add a note to the canvas</DialogTitle>
                    <DialogDescription>
                        Search for a note by name or text.
                    </DialogDescription>
                </DialogHeader>
                <Input
                    autoFocus
                    placeholder="Find a note…"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    onKeyDown={(event) => {
                        if (event.key === 'ArrowDown') {
                            event.preventDefault();
                            setActive((index) =>
                                Math.min(index + 1, results.length - 1),
                            );
                        } else if (event.key === 'ArrowUp') {
                            event.preventDefault();
                            setActive((index) => Math.max(index - 1, 0));
                        } else if (event.key === 'Enter' && results[active]) {
                            event.preventDefault();
                            onPick(results[active].path);
                        }
                    }}
                />
                <ul className="max-h-72 overflow-y-auto">
                    {results.map((result, index) => (
                        <li key={result.id}>
                            <button
                                type="button"
                                data-test="canvas-note-option"
                                className={cn(
                                    'flex w-full flex-col items-start rounded-md px-2 py-1.5 text-left text-sm',
                                    index === active && 'bg-accent',
                                )}
                                onMouseEnter={() => setActive(index)}
                                onClick={() => onPick(result.path)}
                            >
                                <span className="font-medium">
                                    {result.title}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {result.path}
                                </span>
                            </button>
                        </li>
                    ))}
                    {results.length === 0 && (
                        <li className="px-2 py-1.5 text-sm text-muted-foreground">
                            No notes found.
                        </li>
                    )}
                </ul>
            </DialogContent>
        </Dialog>
    );
}

function LinkDialog({
    open,
    onOpenChange,
    onAdd,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onAdd: (url: string) => void;
}) {
    const [url, setUrl] = useState('');
    const valid = /^https?:\/\/\S+$/i.test(url.trim());

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setUrl('');
                onOpenChange(next);
            }}
        >
            <DialogContent
                className="kite-no-drag sm:max-w-md"
                data-test="canvas-link-dialog"
            >
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();

                        if (valid) {
                            onAdd(url.trim());
                            setUrl('');
                        }
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>Add a link</DialogTitle>
                        <DialogDescription>
                            A card for a web page.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-1.5">
                        <Label htmlFor="canvas-link-url">Address</Label>
                        <Input
                            id="canvas-link-url"
                            name="url"
                            autoFocus
                            placeholder="https://"
                            value={url}
                            onChange={(event) => setUrl(event.target.value)}
                        />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={!valid}>
                            Add link
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
