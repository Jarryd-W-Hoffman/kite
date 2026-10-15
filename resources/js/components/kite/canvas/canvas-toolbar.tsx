import {
    Ban,
    FileText,
    Frame,
    Link2,
    Maximize,
    Minus,
    Plus,
    Redo2,
    StickyNote,
    Trash2,
    Undo2,
} from 'lucide-react';
import { useEffect, useState } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PRESET_COLORS } from '@/lib/canvas';
import { isAbortError, requestJson } from '@/lib/kite-http';
import { cn } from '@/lib/utils';
import { index as search } from '@/routes/vault-search';
import type { SearchResult, VaultSummary } from '@/types';

type Props = {
    vault: VaultSummary;
    readOnly: boolean;
    zoom: number;
    hasSelection: boolean;
    canUndo: boolean;
    canRedo: boolean;
    onAddText: () => void;
    onAddNote: (path: string) => void;
    onAddLink: (url: string) => void;
    onAddGroup: () => void;
    onColor: (color: string | null) => void;
    onDelete: () => void;
    onUndo: () => void;
    onRedo: () => void;
    onZoom: (factor: number) => void;
    onFit: () => void;
};

/**
 * The bar floating at the bottom of the canvas: add cards, colour or delete the selection, undo,
 * and zoom.
 */
export function CanvasToolbar(props: Props) {
    const [picking, setPicking] = useState(false);
    const [linking, setLinking] = useState(false);
    const { readOnly } = props;

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

function Tool({
    label,
    testId,
    disabled,
    onClick,
    children,
}: {
    label: string;
    testId?: string;
    disabled?: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            className="size-8"
            aria-label={label}
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
