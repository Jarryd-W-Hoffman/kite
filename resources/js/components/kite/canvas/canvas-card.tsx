import { FileQuestion, FileText, Globe, LockKeyhole } from 'lucide-react';
import type { CSSProperties, PointerEvent as ReactPointerEvent } from 'react';
import { NotePreview } from '@/components/kite/note-preview';
import { colorValue } from '@/lib/canvas';
import type { CanvasFile, CanvasNode, CanvasSide } from '@/lib/canvas';
import { cn } from '@/lib/utils';
import type { VaultSummary } from '@/types';

const SIDES: CanvasSide[] = ['top', 'right', 'bottom', 'left'];

const HANDLE_POSITION: Record<CanvasSide, string> = {
    top: 'left-1/2 -top-[7px] -translate-x-1/2',
    right: 'top-1/2 -right-[7px] -translate-y-1/2',
    bottom: 'left-1/2 -bottom-[7px] -translate-x-1/2',
    left: 'top-1/2 -left-[7px] -translate-y-1/2',
};

type Props = {
    vault: VaultSummary;
    node: CanvasNode;
    file?: CanvasFile;
    /** Text cards as HTML. */
    html?: string;
    selected: boolean;
    editing: boolean;
    readOnly: boolean;
    onTextChange: (text: string) => void;
    onEditEnd: () => void;
    onConnectStart: (event: ReactPointerEvent, side: CanvasSide) => void;
    onResizeStart: (event: ReactPointerEvent) => void;
};

/**
 * One card on the canvas. Moving, selecting and opening are handled by the board around it;
 * the card draws itself, its text editor, and the handles to resize it and to connect it.
 */
export function CanvasCard({
    vault,
    node,
    file,
    html,
    selected,
    editing,
    readOnly,
    onTextChange,
    onEditEnd,
    onConnectStart,
    onResizeStart,
}: Props) {
    const color = colorValue(node.color);
    const style: CSSProperties & Record<'--card-color', string> = {
        left: node.x,
        top: node.y,
        width: node.width,
        height: node.height,
        '--card-color': color ?? 'var(--border)',
    };

    if (node.type === 'group') {
        return (
            <div
                data-node-id={node.id}
                data-test="canvas-group"
                className={cn(
                    'group/card absolute rounded-xl border-2 border-[var(--card-color)]',
                    selected &&
                        'ring-2 ring-primary ring-offset-2 ring-offset-background',
                )}
                style={{
                    ...style,
                    backgroundColor: color
                        ? `color-mix(in srgb, ${color} 8%, transparent)`
                        : 'color-mix(in srgb, var(--muted) 45%, transparent)',
                }}
            >
                {node.label && (
                    <div className="absolute -top-7 left-0 max-w-full truncate rounded-md bg-background/80 px-2 py-0.5 text-sm font-medium">
                        {node.label}
                    </div>
                )}
                {!readOnly && (
                    <Handles
                        onConnectStart={onConnectStart}
                        onResizeStart={onResizeStart}
                    />
                )}
            </div>
        );
    }

    return (
        <div
            data-node-id={node.id}
            data-test="canvas-card"
            data-card-type={node.type}
            className={cn(
                'group/card absolute flex flex-col overflow-visible rounded-xl border-2 border-[var(--card-color)] bg-card shadow-kite-sm',
                selected &&
                    'ring-2 ring-primary ring-offset-2 ring-offset-background',
            )}
            style={style}
        >
            <div className="min-h-0 flex-1 overflow-hidden rounded-[10px]">
                {node.type === 'text' &&
                    (editing ? (
                        <textarea
                            autoFocus
                            defaultValue={node.text}
                            aria-label="Card text"
                            data-test="canvas-card-editor"
                            className="size-full resize-none bg-transparent p-3 font-mono text-sm outline-none"
                            onChange={(event) =>
                                onTextChange(event.target.value)
                            }
                            onBlur={onEditEnd}
                            onKeyDown={(event) => {
                                event.stopPropagation();

                                if (event.key === 'Escape') {
                                    event.currentTarget.blur();
                                }
                            }}
                            onPointerDown={(event) => event.stopPropagation()}
                        />
                    ) : (
                        <Scrollable>
                            {html === undefined ? (
                                <p className="p-3 text-sm whitespace-pre-wrap">
                                    {node.text}
                                </p>
                            ) : (
                                <NotePreview
                                    vault={vault}
                                    html={html}
                                    className="kite-canvas-text p-3"
                                />
                            )}
                        </Scrollable>
                    ))}

                {node.type === 'file' && (
                    <FileContent vault={vault} path={node.file} file={file} />
                )}

                {node.type === 'link' && (
                    <div className="flex h-full flex-col justify-center gap-1 p-3">
                        <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <Globe className="size-3.5" />
                            {hostname(node.url)}
                        </span>
                        <span className="truncate text-sm text-primary">
                            {node.url}
                        </span>
                    </div>
                )}
            </div>

            {!readOnly && (
                <Handles
                    onConnectStart={onConnectStart}
                    onResizeStart={onResizeStart}
                />
            )}
        </div>
    );
}

/** Card content that scrolls on its own instead of moving the canvas. */
function Scrollable({ children }: { children: React.ReactNode }) {
    return (
        <div data-canvas-scroll className="h-full overflow-auto">
            {children}
        </div>
    );
}

function FileContent({
    vault,
    path,
    file,
}: {
    vault: VaultSummary;
    path: string;
    file?: CanvasFile;
}) {
    if (!file) {
        return <div className="size-full animate-pulse bg-muted/60" />;
    }

    if (file.kind === 'note') {
        return (
            <div className="flex h-full flex-col">
                <div className="flex shrink-0 items-center gap-1.5 border-b px-3 py-1.5 text-xs font-medium text-muted-foreground">
                    {file.locked ? (
                        <LockKeyhole className="size-3.5" />
                    ) : (
                        <FileText className="size-3.5" />
                    )}
                    <span className="truncate">{file.title}</span>
                </div>
                {file.locked ? (
                    <p className="p-3 text-sm text-muted-foreground">
                        This note is locked. Open it to unlock it.
                    </p>
                ) : (
                    <Scrollable>
                        <NotePreview
                            vault={vault}
                            html={file.html}
                            className="p-3"
                        />
                    </Scrollable>
                )}
            </div>
        );
    }

    if (file.kind === 'image') {
        return (
            <img
                src={file.url}
                alt={path.split('/').pop()}
                draggable={false}
                className="size-full object-contain"
            />
        );
    }

    return (
        <div
            className={cn(
                'flex h-full items-center gap-2 p-3 text-sm',
                file.kind === 'missing' && 'text-muted-foreground',
            )}
        >
            {file.kind === 'missing' ? (
                <FileQuestion className="size-4 shrink-0" />
            ) : (
                <FileText className="size-4 shrink-0" />
            )}
            <span className="truncate">
                {file.kind === 'missing'
                    ? `Not found: ${path}`
                    : path.split('/').pop()}
            </span>
        </div>
    );
}

function Handles({
    onConnectStart,
    onResizeStart,
}: {
    onConnectStart: (event: ReactPointerEvent, side: CanvasSide) => void;
    onResizeStart: (event: ReactPointerEvent) => void;
}) {
    return (
        <>
            {SIDES.map((side) => (
                <span
                    key={side}
                    role="presentation"
                    data-connect-side={side}
                    data-test={`canvas-connect-${side}`}
                    className={cn(
                        'absolute z-10 size-3.5 cursor-crosshair rounded-full border-2 border-primary bg-background opacity-0 transition-opacity group-hover/card:opacity-100 hover:scale-125',
                        HANDLE_POSITION[side],
                    )}
                    onPointerDown={(event) => onConnectStart(event, side)}
                />
            ))}
            <span
                role="presentation"
                data-test="canvas-resize"
                className="absolute -right-1.5 -bottom-1.5 z-10 size-4 cursor-nwse-resize"
                onPointerDown={onResizeStart}
            />
        </>
    );
}

function hostname(url: string): string {
    try {
        return new URL(url).hostname;
    } catch {
        return url;
    }
}
