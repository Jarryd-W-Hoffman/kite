import { router } from '@inertiajs/react';
import {
    useCallback,
    useEffect,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import type {
    DragEvent,
    KeyboardEvent as ReactKeyboardEvent,
    PointerEvent as ReactPointerEvent,
} from 'react';
import { CanvasCard } from '@/components/kite/canvas/canvas-card';
import {
    CanvasEdges,
    layoutEdges,
} from '@/components/kite/canvas/canvas-edges';
import { CanvasToolbar } from '@/components/kite/canvas/canvas-toolbar';
import type { useCanvasDocument } from '@/hooks/use-canvas-document';
import { useOpenExternalLink } from '@/hooks/use-open-external-link';
import {
    DEFAULT_SIZES,
    anchor,
    clampZoom,
    contains,
    edgeGeometry,
    fitViewport,
    freeSpot,
    intersects,
    nearestSide,
    newId,
    snap,
} from '@/lib/canvas';
import type {
    CanvasData,
    CanvasFile,
    CanvasNode,
    CanvasSide,
    Point,
    Rect,
    Viewport,
} from '@/lib/canvas';
import { requestJson } from '@/lib/kite-http';
import { index as describeFiles } from '@/routes/canvas-cards';
import { show as showNote } from '@/routes/notes';
import { store as preview } from '@/routes/vault-preview';
import type { TreeNote, VaultSummary } from '@/types';

/** What the file tree puts on a dragged note: its id. */
const TREE_DRAG_TYPE = 'application/x-kite-tree-item';

const MIN_SIZE = { width: 80, height: 40 };

/** How far a press has to move before it counts as a drag rather than a click. */
const DRAG_THRESHOLD = 3;

type Gesture =
    | { kind: 'pan'; start: Point; origin: Viewport }
    | {
          kind: 'move';
          start: Point;
          origins: Map<string, Point>;
          moved: boolean;
      }
    | { kind: 'resize'; id: string; start: Point; origin: Rect }
    | { kind: 'connect'; from: string; side: CanvasSide }
    | { kind: 'marquee'; start: Point; base: Set<string> };

type Doc = ReturnType<typeof useCanvasDocument>;

type Props = {
    vault: VaultSummary;
    tree: TreeNote[];
    doc: Doc;
    initialFiles: Record<string, CanvasFile>;
    initialTexts: Record<string, string>;
};

/**
 * The canvas: cards and their connections on an endless surface.
 *
 * Drag empty space to select cards in a box, hold Space (or the middle button) and drag to pan,
 * scroll to pan and pinch or Ctrl/Cmd-scroll to zoom. Double-click empty space for a new card,
 * drag a card's side handle to connect it (or, dropped on empty space, to a new card), and drag
 * notes in from the file tree.
 */
export function CanvasBoard({
    vault,
    tree,
    doc,
    initialFiles,
    initialTexts,
}: Props) {
    const openExternalLink = useOpenExternalLink();
    const { data, readOnly } = doc;
    const container = useRef<HTMLDivElement>(null);
    const [size, setSize] = useState({ width: 0, height: 0 });
    const [viewport, setViewport] = useState<Viewport | null>(null);
    const [selected, setSelected] = useState<Set<string>>(() => new Set());
    const [editing, setEditing] = useState<string | null>(null);
    const [editingLabel, setEditingLabel] = useState<string | null>(null);
    const [files, setFiles] = useState(initialFiles);
    const [texts, setTexts] = useState(initialTexts);
    const [connecting, setConnecting] = useState<{
        from: string;
        side: CanvasSide;
        point: Point;
    } | null>(null);
    const [marquee, setMarquee] = useState<Rect | null>(null);
    const gesture = useRef<Gesture | null>(null);
    const space = useRef(false);

    const view = viewport ?? { x: 0, y: 0, zoom: 1 };

    useLayoutEffect(() => {
        const element = container.current;

        if (!element) {
            return;
        }

        const observer = new ResizeObserver(([entry]) =>
            setSize({
                width: entry.contentRect.width,
                height: entry.contentRect.height,
            }),
        );

        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    // Start with every card in view.
    if (viewport === null && size.width > 0) {
        setViewport(fitViewport(data.nodes, size.width, size.height));
    }

    const toCanvas = useCallback(
        (clientX: number, clientY: number): Point => {
            const rect = container.current?.getBoundingClientRect();

            return {
                x: (clientX - (rect?.left ?? 0) - view.x) / view.zoom,
                y: (clientY - (rect?.top ?? 0) - view.y) / view.zoom,
            };
        },
        [view.x, view.y, view.zoom],
    );

    const center = (): Point => ({
        x: (size.width / 2 - view.x) / view.zoom,
        y: (size.height / 2 - view.y) / view.zoom,
    });

    const nodeById = useMemo(
        () => new Map(data.nodes.map((node) => [node.id, node])),
        [data.nodes],
    );
    const layouts = useMemo(
        () => layoutEdges(data.nodes, data.edges),
        [data.nodes, data.edges],
    );

    // Groups sit under the cards in them: larger groups first, then everything else.
    const ordered = useMemo(
        () => [
            ...data.nodes
                .filter((node) => node.type === 'group')
                .sort((a, b) => b.width * b.height - a.width * a.height),
            ...data.nodes.filter((node) => node.type !== 'group'),
        ],
        [data.nodes],
    );

    const describe = (paths: string[]) => {
        const missing = paths.filter((path) => !(path in files));

        if (missing.length > 0) {
            void requestJson<{ files: Record<string, CanvasFile> }>(
                describeFiles(vault.id, { query: { paths: missing } }),
            ).then((response) =>
                setFiles((current) => ({ ...current, ...response.files })),
            );
        }
    };

    const addNode = (node: CanvasNode, select = true) => {
        doc.change((current) => ({
            ...current,
            nodes: [...current.nodes, node],
        }));

        if (select) {
            setSelected(new Set([node.id]));
        }

        if (node.type === 'file') {
            describe([node.file]);
        }
    };

    /**
     * A new card centred on a point, on the grid. Cards added from the toolbar (`avoid`) move
     * off any cards already there.
     */
    const place = (type: CanvasNode['type'], at: Point, avoid = false) => {
        const { width, height } = DEFAULT_SIZES[type];
        const card = {
            x: snap(at.x - width / 2),
            y: snap(at.y - height / 2),
            width,
            height,
        };

        return { id: newId(), ...(avoid ? freeSpot(card, data.nodes) : card) };
    };

    const addText = (at?: Point) => {
        const node: CanvasNode = {
            ...place('text', at ?? center(), at === undefined),
            type: 'text',
            text: '',
        };

        addNode(node);
        startEditing(node.id);

        return node;
    };

    const addFile = (path: string, at?: Point) =>
        addNode({
            ...place('file', at ?? center(), at === undefined),
            type: 'file',
            file: path,
        });

    const addLink = (url: string) =>
        addNode({ ...place('link', center(), true), type: 'link', url });

    const addGroup = () => {
        const chosen = data.nodes.filter((node) => selected.has(node.id));
        const box = chosen.length > 0 ? fitAround(chosen) : null;

        addNode(
            box
                ? { id: newId(), type: 'group', label: 'Group', ...box }
                : {
                      ...place('group', center(), true),
                      type: 'group',
                      label: 'Group',
                  },
        );
    };

    const startEditing = (id: string) => {
        doc.begin();
        setEditing(id);
    };

    const finishEditing = () => {
        const node = editing === null ? undefined : nodeById.get(editing);

        setEditing(null);

        if (node?.type === 'text' && node.text.trim() === '') {
            // An empty card goes away rather than cluttering the canvas.
            doc.preview((current) => without(current, new Set([node.id])));
            doc.end();

            return;
        }

        doc.end();

        if (node?.type === 'text') {
            void requestJson<{ html: string }>(preview(vault.id), {
                contents: node.text,
            }).then((response) =>
                setTexts((current) => ({
                    ...current,
                    [node.id]: response.html,
                })),
            );
        }
    };

    const deleteSelection = () => {
        if (selected.size > 0) {
            doc.change((current) => without(current, selected));
            setSelected(new Set());
        }
    };

    const setColor = (color: string | null) =>
        doc.change((current) => ({
            nodes: current.nodes.map((node) =>
                selected.has(node.id) ? withColor(node, color) : node,
            ),
            edges: current.edges.map((edge) =>
                selected.has(edge.id) ? withColor(edge, color) : edge,
            ),
        }));

    const zoomAround = (
        factor: number,
        focus: Point = { x: size.width / 2, y: size.height / 2 },
    ) =>
        setViewport((current) => {
            const from = current ?? view;
            const zoom = clampZoom(from.zoom * factor);

            return {
                zoom,
                x: focus.x - ((focus.x - from.x) / from.zoom) * zoom,
                y: focus.y - ((focus.y - from.y) / from.zoom) * zoom,
            };
        });

    // Wheel: pinch or Ctrl/Cmd-scroll zooms, scrolling pans (unless a card's content scrolls).
    useEffect(() => {
        const element = container.current;

        if (!element) {
            return;
        }

        const onWheel = (event: WheelEvent) => {
            const rect = element.getBoundingClientRect();

            if (event.ctrlKey || event.metaKey) {
                event.preventDefault();
                zoomAround(Math.exp(-event.deltaY * 0.01), {
                    x: event.clientX - rect.left,
                    y: event.clientY - rect.top,
                });

                return;
            }

            const scroller = (event.target as HTMLElement).closest<HTMLElement>(
                '[data-canvas-scroll]',
            );

            if (scroller && scroller.scrollHeight > scroller.clientHeight) {
                return;
            }

            event.preventDefault();
            setViewport((current) =>
                current
                    ? {
                          ...current,
                          x: current.x - event.deltaX,
                          y: current.y - event.deltaY,
                      }
                    : current,
            );
        };

        element.addEventListener('wheel', onWheel, { passive: false });

        return () => element.removeEventListener('wheel', onWheel);
    });

    // Space held: drag pans.
    useEffect(() => {
        const down = (event: KeyboardEvent) => {
            if (
                event.code === 'Space' &&
                !(event.target as HTMLElement).closest('input, textarea')
            ) {
                space.current = true;
            }
        };
        const up = (event: KeyboardEvent) => {
            if (event.code === 'Space') {
                space.current = false;
            }
        };

        window.addEventListener('keydown', down);
        window.addEventListener('keyup', up);

        return () => {
            window.removeEventListener('keydown', down);
            window.removeEventListener('keyup', up);
        };
    }, []);

    const onPointerDown = (event: ReactPointerEvent<HTMLDivElement>) => {
        if (event.button === 2) {
            return;
        }

        container.current?.focus({ preventScroll: true });
        event.currentTarget.setPointerCapture(event.pointerId);

        if (event.button === 1 || space.current) {
            gesture.current = {
                kind: 'pan',
                start: { x: event.clientX, y: event.clientY },
                origin: view,
            };

            return;
        }

        const point = toCanvas(event.clientX, event.clientY);
        const id = (event.target as HTMLElement).closest<HTMLElement>(
            '[data-node-id]',
        )?.dataset.nodeId;

        if (id === undefined) {
            const base = event.shiftKey ? new Set(selected) : new Set<string>();

            setSelected(base);
            gesture.current = { kind: 'marquee', start: point, base };

            return;
        }

        if (event.shiftKey && selected.has(id)) {
            setSelected(new Set([...selected].filter((other) => other !== id)));

            return;
        }

        const selection = selected.has(id)
            ? selected
            : event.shiftKey
              ? new Set([...selected, id])
              : new Set([id]);

        setSelected(selection);

        if (readOnly) {
            return;
        }

        // Moving a group takes the cards inside it along.
        const moving = new Set(selection);

        for (const node of data.nodes) {
            if (node.type === 'group' && selection.has(node.id)) {
                data.nodes
                    .filter((other) => other !== node && contains(node, other))
                    .forEach((other) => moving.add(other.id));
            }
        }

        gesture.current = {
            kind: 'move',
            start: point,
            moved: false,
            origins: new Map(
                data.nodes
                    .filter((node) => moving.has(node.id))
                    .map((node) => [node.id, { x: node.x, y: node.y }]),
            ),
        };
    };

    const onConnectStart = (
        event: ReactPointerEvent,
        id: string,
        side: CanvasSide,
    ) => {
        event.stopPropagation();
        container.current?.setPointerCapture(event.pointerId);
        gesture.current = { kind: 'connect', from: id, side };
        setConnecting({
            from: id,
            side,
            point: toCanvas(event.clientX, event.clientY),
        });
    };

    const onResizeStart = (event: ReactPointerEvent, node: CanvasNode) => {
        event.stopPropagation();
        container.current?.setPointerCapture(event.pointerId);
        doc.begin();
        gesture.current = {
            kind: 'resize',
            id: node.id,
            start: toCanvas(event.clientX, event.clientY),
            origin: {
                x: node.x,
                y: node.y,
                width: node.width,
                height: node.height,
            },
        };
    };

    const onPointerMove = (event: ReactPointerEvent<HTMLDivElement>) => {
        const current = gesture.current;

        if (!current) {
            return;
        }

        if (current.kind === 'pan') {
            setViewport({
                ...current.origin,
                x: current.origin.x + event.clientX - current.start.x,
                y: current.origin.y + event.clientY - current.start.y,
            });

            return;
        }

        const point = toCanvas(event.clientX, event.clientY);

        if (current.kind === 'move') {
            const dx = point.x - current.start.x;
            const dy = point.y - current.start.y;

            if (
                !current.moved &&
                Math.hypot(dx, dy) * view.zoom < DRAG_THRESHOLD
            ) {
                return;
            }

            if (!current.moved) {
                current.moved = true;
                doc.begin();
            }

            doc.preview((data) => ({
                ...data,
                nodes: data.nodes.map((node) => {
                    const origin = current.origins.get(node.id);

                    return origin
                        ? { ...node, x: origin.x + dx, y: origin.y + dy }
                        : node;
                }),
            }));
        } else if (current.kind === 'resize') {
            doc.preview((data) => ({
                ...data,
                nodes: data.nodes.map((node) =>
                    node.id === current.id
                        ? {
                              ...node,
                              width: Math.max(
                                  MIN_SIZE.width,
                                  current.origin.width +
                                      point.x -
                                      current.start.x,
                              ),
                              height: Math.max(
                                  MIN_SIZE.height,
                                  current.origin.height +
                                      point.y -
                                      current.start.y,
                              ),
                          }
                        : node,
                ),
            }));
        } else if (current.kind === 'connect') {
            setConnecting({ from: current.from, side: current.side, point });
        } else if (current.kind === 'marquee') {
            const box = {
                x: Math.min(current.start.x, point.x),
                y: Math.min(current.start.y, point.y),
                width: Math.abs(point.x - current.start.x),
                height: Math.abs(point.y - current.start.y),
            };

            setMarquee(box);
            setSelected(
                new Set([
                    ...current.base,
                    ...data.nodes
                        .filter((node) => intersects(box, node))
                        .map((node) => node.id),
                ]),
            );
        }
    };

    const onPointerUp = (event: ReactPointerEvent<HTMLDivElement>) => {
        const current = gesture.current;

        gesture.current = null;
        setMarquee(null);
        setConnecting(null);

        if (!current) {
            return;
        }

        if (
            (current.kind === 'move' && current.moved) ||
            current.kind === 'resize'
        ) {
            const changed =
                current.kind === 'move'
                    ? new Set(current.origins.keys())
                    : new Set([current.id]);

            doc.preview((data) => ({
                ...data,
                nodes: data.nodes.map((node) =>
                    changed.has(node.id)
                        ? {
                              ...node,
                              x: snap(node.x),
                              y: snap(node.y),
                              width: snap(node.width) || 20,
                              height: snap(node.height) || 20,
                          }
                        : node,
                ),
            }));
            doc.end();
        } else if (current.kind === 'connect') {
            const point = toCanvas(event.clientX, event.clientY);
            const targetId = document
                .elementFromPoint(event.clientX, event.clientY)
                ?.closest<HTMLElement>('[data-node-id]')?.dataset.nodeId;
            const target =
                targetId === undefined ? undefined : nodeById.get(targetId);

            if (target && target.id !== current.from) {
                connect(
                    current.from,
                    current.side,
                    target.id,
                    nearestSide(target, point),
                );
            } else if (!target) {
                // Dropped on empty space: connect to a new card there.
                const node = addText(point);

                connect(current.from, current.side, node.id, null);
            }
        }
    };

    const connect = (
        from: string,
        fromSide: CanvasSide,
        to: string,
        toSide: CanvasSide | null,
    ) =>
        doc.change((current) => ({
            ...current,
            edges: [
                ...current.edges,
                {
                    id: newId(),
                    fromNode: from,
                    fromSide,
                    toNode: to,
                    ...(toSide ? { toSide } : {}),
                    toEnd: 'arrow',
                },
            ],
        }));

    const onDoubleClick = (event: React.MouseEvent<HTMLDivElement>) => {
        const id = (event.target as HTMLElement).closest<HTMLElement>(
            '[data-node-id]',
        )?.dataset.nodeId;
        const node = id === undefined ? undefined : nodeById.get(id);

        if (!node) {
            if (!readOnly) {
                addText(toCanvas(event.clientX, event.clientY));
            }

            return;
        }

        if (node.type === 'text' && !readOnly) {
            startEditing(node.id);
        } else if (node.type === 'group' && !readOnly) {
            setEditingLabel(node.id);
        } else if (node.type === 'file') {
            const file = files[node.file];

            if (file?.kind === 'note') {
                router.visit(
                    showNote({ vault: vault.id, note: file.note_id }).url,
                );
            } else if (file?.kind === 'image' || file?.kind === 'file') {
                window.open(file.url, '_blank', 'noopener');
            }
        } else if (node.type === 'link') {
            openExternalLink(node.url);
        }
    };

    const onKeyDown = (event: ReactKeyboardEvent<HTMLDivElement>) => {
        const mod = event.metaKey || event.ctrlKey;

        if ((event.target as HTMLElement).closest('input, textarea')) {
            return;
        }

        if (
            (event.key === 'Delete' || event.key === 'Backspace') &&
            !readOnly
        ) {
            event.preventDefault();
            deleteSelection();
        } else if (mod && event.key.toLowerCase() === 'z' && !readOnly) {
            event.preventDefault();

            if (event.shiftKey) {
                doc.redo();
            } else {
                doc.undo();
            }
        } else if (mod && event.key.toLowerCase() === 'y' && !readOnly) {
            event.preventDefault();
            doc.redo();
        } else if (mod && event.key.toLowerCase() === 'a') {
            event.preventDefault();
            setSelected(
                new Set([
                    ...data.nodes.map((node) => node.id),
                    ...data.edges.map((edge) => edge.id),
                ]),
            );
        } else if (event.key === 'Escape') {
            setSelected(new Set());
        } else if (event.key === 'Enter' && selected.size === 1 && !readOnly) {
            const node = nodeById.get([...selected][0]);

            if (node?.type === 'text') {
                event.preventDefault();
                startEditing(node.id);
            }
        }
    };

    const onDragOver = (event: DragEvent<HTMLDivElement>) => {
        if (!readOnly && event.dataTransfer.types.includes(TREE_DRAG_TYPE)) {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'copy';
        }
    };

    const onDrop = (event: DragEvent<HTMLDivElement>) => {
        const note = tree.find(
            (item) =>
                String(item.id) === event.dataTransfer.getData(TREE_DRAG_TYPE),
        );

        if (note && !readOnly) {
            event.preventDefault();
            addFile(note.path, toCanvas(event.clientX, event.clientY));
        }
    };

    const pendingEdge =
        connecting && nodeById.get(connecting.from)
            ? {
                  path: edgeGeometry(
                      anchor(
                          nodeById.get(connecting.from) as Rect,
                          connecting.side,
                      ),
                      connecting.side,
                      connecting.point,
                      null,
                  ).path,
              }
            : null;

    const labelEdge =
        editingLabel === null
            ? undefined
            : layouts.find((layout) => layout.edge.id === editingLabel);
    const labelGroup =
        editingLabel === null ? undefined : nodeById.get(editingLabel);

    return (
        <div className="relative flex min-h-0 flex-1 flex-col">
            <div
                ref={container}
                tabIndex={0}
                aria-label="Canvas"
                data-test="canvas-board"
                className="kite-canvas relative min-h-0 flex-1 touch-none overflow-hidden outline-none select-none"
                style={{
                    backgroundImage:
                        'radial-gradient(circle, color-mix(in srgb, var(--muted-foreground) 30%, transparent) 1px, transparent 1px)',
                    backgroundSize: `${20 * view.zoom}px ${20 * view.zoom}px`,
                    backgroundPosition: `${view.x}px ${view.y}px`,
                }}
                onPointerDown={onPointerDown}
                onPointerMove={onPointerMove}
                onPointerUp={onPointerUp}
                onPointerCancel={onPointerUp}
                onDoubleClick={onDoubleClick}
                onKeyDown={onKeyDown}
                onDragOver={onDragOver}
                onDrop={onDrop}
            >
                <div
                    className="absolute top-0 left-0 origin-top-left"
                    style={{
                        transform: `translate(${view.x}px, ${view.y}px) scale(${view.zoom})`,
                    }}
                >
                    {ordered
                        .filter((node) => node.type === 'group')
                        .map((node) => renderCard(node))}
                    <CanvasEdges
                        layouts={layouts}
                        selected={selected}
                        readOnly={readOnly}
                        pending={pendingEdge}
                        onSelect={(id, additive) =>
                            setSelected(
                                additive
                                    ? new Set([...selected, id])
                                    : new Set([id]),
                            )
                        }
                        onEditLabel={setEditingLabel}
                    />
                    {ordered
                        .filter((node) => node.type !== 'group')
                        .map((node) => renderCard(node))}

                    {layouts.map(({ edge, middle }) =>
                        edge.label && edge.id !== editingLabel ? (
                            <div
                                key={edge.id}
                                className="pointer-events-none absolute -translate-x-1/2 -translate-y-1/2 rounded-md bg-background px-1.5 py-0.5 text-xs whitespace-nowrap shadow-kite-sm"
                                style={{ left: middle.x, top: middle.y }}
                                data-test="canvas-edge-label"
                            >
                                {edge.label}
                            </div>
                        ) : null,
                    )}

                    {(labelEdge || labelGroup) && (
                        <input
                            autoFocus
                            aria-label={
                                labelEdge ? 'Connection label' : 'Group name'
                            }
                            data-test="canvas-label-editor"
                            defaultValue={
                                labelEdge
                                    ? (labelEdge.edge.label ?? '')
                                    : labelGroup?.type === 'group'
                                      ? (labelGroup.label ?? '')
                                      : ''
                            }
                            className="absolute z-20 w-48 rounded-md border bg-background px-2 py-1 text-sm shadow-kite-sm outline-none focus:ring-2 focus:ring-ring"
                            style={
                                labelEdge
                                    ? {
                                          left: labelEdge.middle.x - 96,
                                          top: labelEdge.middle.y - 16,
                                      }
                                    : {
                                          left: labelGroup?.x ?? 0,
                                          top: (labelGroup?.y ?? 0) - 34,
                                      }
                            }
                            onPointerDown={(event) => event.stopPropagation()}
                            onKeyDown={(event) => {
                                event.stopPropagation();

                                if (
                                    event.key === 'Enter' ||
                                    event.key === 'Escape'
                                ) {
                                    event.currentTarget.blur();
                                }
                            }}
                            onBlur={(event) => {
                                const label = event.target.value.trim();
                                const id = editingLabel;

                                setEditingLabel(null);
                                doc.change((current) => ({
                                    nodes: current.nodes.map((node) =>
                                        node.id === id && node.type === 'group'
                                            ? withLabel(node, label)
                                            : node,
                                    ),
                                    edges: current.edges.map((edge) =>
                                        edge.id === id
                                            ? withLabel(edge, label)
                                            : edge,
                                    ),
                                }));
                            }}
                        />
                    )}

                    {marquee && (
                        <div
                            className="pointer-events-none absolute rounded-sm border border-primary bg-primary/10"
                            style={{
                                left: marquee.x,
                                top: marquee.y,
                                width: marquee.width,
                                height: marquee.height,
                            }}
                        />
                    )}
                </div>

                {data.nodes.length === 0 && (
                    <div className="pointer-events-none absolute inset-0 flex items-center justify-center text-sm text-muted-foreground">
                        {readOnly
                            ? 'This canvas is empty.'
                            : 'Double-click to add a card, or drag notes here from the sidebar.'}
                    </div>
                )}
            </div>

            <CanvasToolbar
                vault={vault}
                readOnly={readOnly}
                zoom={view.zoom}
                hasSelection={selected.size > 0}
                canUndo={doc.canUndo}
                canRedo={doc.canRedo}
                onAddText={() => addText()}
                onAddNote={(path) => addFile(path)}
                onAddLink={addLink}
                onAddGroup={addGroup}
                onColor={setColor}
                onDelete={deleteSelection}
                onUndo={doc.undo}
                onRedo={doc.redo}
                onZoom={(factor) => zoomAround(factor)}
                onFit={() =>
                    setViewport(
                        fitViewport(data.nodes, size.width, size.height),
                    )
                }
            />
        </div>
    );

    function renderCard(node: CanvasNode) {
        return (
            <CanvasCard
                key={node.id}
                vault={vault}
                node={node}
                file={node.type === 'file' ? files[node.file] : undefined}
                html={texts[node.id]}
                selected={selected.has(node.id)}
                editing={editing === node.id}
                readOnly={readOnly}
                onTextChange={(text) =>
                    doc.preview((current) => ({
                        ...current,
                        nodes: current.nodes.map((other) =>
                            other.id === node.id && other.type === 'text'
                                ? { ...other, text }
                                : other,
                        ),
                    }))
                }
                onEditEnd={finishEditing}
                onConnectStart={(event, side) =>
                    onConnectStart(event, node.id, side)
                }
                onResizeStart={(event) => onResizeStart(event, node)}
            />
        );
    }
}

/** The canvas without some cards and connections, and without connections to removed cards. */
function without(data: CanvasData, ids: Set<string>): CanvasData {
    const nodes = data.nodes.filter((node) => !ids.has(node.id));
    const kept = new Set(nodes.map((node) => node.id));

    return {
        nodes,
        edges: data.edges.filter(
            (edge) =>
                !ids.has(edge.id) &&
                kept.has(edge.fromNode) &&
                kept.has(edge.toNode),
        ),
    };
}

function withColor<T extends { color?: string }>(
    item: T,
    color: string | null,
): T {
    const { color: _previous, ...rest } = item;

    return (color === null ? rest : { ...rest, color }) as T;
}

function withLabel<T extends { label?: string }>(item: T, label: string): T {
    const { label: _previous, ...rest } = item;

    return (label === '' ? rest : { ...rest, label }) as T;
}

/** A group's box around some cards, with room for them to breathe. */
function fitAround(nodes: Rect[]): Rect {
    const left = Math.min(...nodes.map((node) => node.x));
    const top = Math.min(...nodes.map((node) => node.y));
    const right = Math.max(...nodes.map((node) => node.x + node.width));
    const bottom = Math.max(...nodes.map((node) => node.y + node.height));

    return {
        x: snap(left - 40),
        y: snap(top - 40),
        width: snap(right - left + 80),
        height: snap(bottom - top + 80),
    };
}
