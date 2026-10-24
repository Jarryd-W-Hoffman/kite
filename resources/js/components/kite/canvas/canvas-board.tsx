import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { DragEvent, KeyboardEvent as ReactKeyboardEvent } from 'react';
import { CanvasCard } from '@/components/kite/canvas/canvas-card';
import {
    CanvasEdges,
    layoutEdges,
} from '@/components/kite/canvas/canvas-edges';
import { CanvasLabelEditor } from '@/components/kite/canvas/canvas-label-editor';
import { CanvasToolbar } from '@/components/kite/canvas/canvas-toolbar';
import type { useCanvasDocument } from '@/hooks/use-canvas-document';
import { useCanvasGestures } from '@/hooks/use-canvas-gestures';
import { useCanvasViewport } from '@/hooks/use-canvas-viewport';
import { useOpenExternalLink } from '@/hooks/use-open-external-link';
import {
    DEFAULT_SIZES,
    anchor,
    edgeGeometry,
    fitAround,
    freeSpot,
    nearestSide,
    newId,
    reversed,
    shapeOf,
    SHAPES,
    snap,
    without,
    withColor,
    withEnds,
    withLabel,
    withLineStyle,
    withShape,
} from '@/lib/canvas';
import type {
    CanvasEdge,
    CanvasFile,
    CanvasNode,
    CanvasShape,
    CanvasSide,
    Point,
    Rect,
} from '@/lib/canvas';
import { requestJson } from '@/lib/kite-http';
import { index as describeFiles } from '@/routes/canvas-cards';
import { show as showNote } from '@/routes/notes';
import { store as preview } from '@/routes/vault-preview';
import type { TreeNote, VaultSummary } from '@/types';

/** What the file tree puts on a dragged note: its id. */
const TREE_DRAG_TYPE = 'application/x-kite-tree-item';

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
    const viewport = useCanvasViewport(data.nodes);
    const { container, view, toCanvas, center, zoomAround, fit } = viewport;
    const [selected, setSelected] = useState<Set<string>>(() => new Set());
    const [editing, setEditing] = useState<string | null>(null);
    const [editingLabel, setEditingLabel] = useState<string | null>(null);
    const [files, setFiles] = useState(initialFiles);
    const [texts, setTexts] = useState(initialTexts);

    const nodeById = useMemo(
        () => new Map(data.nodes.map((node) => [node.id, node])),
        [data.nodes],
    );
    const {
        connecting,
        marquee,
        onPointerDown,
        onPointerMove,
        onPointerUp,
        onConnectStart,
        onResizeStart,
    } = useCanvasGestures({
        doc,
        viewport,
        selected,
        setSelected,
        nodeById,
        // Declared further down, so it is reached through a closure here.
        onConnectEnd: (...args) => finishConnection(...args),
    });
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
        const box = fitAround(
            data.nodes.filter((node) => selected.has(node.id)),
        );

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

        if (
            node?.type === 'text' &&
            node.text.trim() === '' &&
            shapeOf(node) === null
        ) {
            // An empty card goes away rather than cluttering the canvas. An empty shape stays,
            // since a flowchart can use one on its own, like a junction circle.
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

    const addShape = (shape: CanvasShape) => {
        const { width, height } =
            SHAPES.find((option) => option.shape === shape) ?? SHAPES[0];
        const at = center();
        const card = freeSpot(
            {
                x: snap(at.x - width / 2),
                y: snap(at.y - height / 2),
                width,
                height,
            },
            data.nodes,
        );
        const node = withShape<CanvasNode>(
            { id: newId(), ...card, type: 'text', text: '' },
            shape,
        );

        addNode(node);
        startEditing(node.id);
    };

    const setShape = (shape: CanvasShape | null) =>
        doc.change((current) => ({
            ...current,
            nodes: current.nodes.map((node) =>
                selected.has(node.id) && node.type === 'text'
                    ? withShape(node, shape)
                    : node,
            ),
        }));

    const changeEdges = (update: (edge: CanvasEdge) => CanvasEdge) =>
        doc.change((current) => ({
            ...current,
            edges: current.edges.map((edge) =>
                selected.has(edge.id) ? update(edge) : edge,
            ),
        }));

    const setColor = (color: string | null) =>
        doc.change((current) => ({
            nodes: current.nodes.map((node) =>
                selected.has(node.id) ? withColor(node, color) : node,
            ),
            edges: current.edges.map((edge) =>
                selected.has(edge.id) ? withColor(edge, color) : edge,
            ),
        }));

    const finishConnection = (
        from: string,
        side: CanvasSide,
        point: Point,
        target: CanvasNode | undefined,
    ) => {
        if (target && target.id !== from) {
            connect(from, side, target.id, nearestSide(target, point));
        } else if (!target) {
            // Dropped on empty space: connect to a new card there.
            const node = addText(point);

            connect(from, side, node.id, null);
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
                        <CanvasLabelEditor
                            name={labelEdge ? 'Connection label' : 'Group name'}
                            label={
                                labelEdge
                                    ? (labelEdge.edge.label ?? '')
                                    : labelGroup?.type === 'group'
                                      ? (labelGroup.label ?? '')
                                      : ''
                            }
                            at={
                                labelEdge
                                    ? {
                                          x: labelEdge.middle.x - 96,
                                          y: labelEdge.middle.y - 16,
                                      }
                                    : {
                                          x: labelGroup?.x ?? 0,
                                          y: (labelGroup?.y ?? 0) - 34,
                                      }
                            }
                            onDone={(label) => {
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
                selectedTextCards={
                    data.nodes.filter(
                        (node) => node.type === 'text' && selected.has(node.id),
                    ).length
                }
                selectedEdges={data.edges.filter((edge) =>
                    selected.has(edge.id),
                )}
                canUndo={doc.canUndo}
                canRedo={doc.canRedo}
                onAddText={() => addText()}
                onAddNote={(path) => addFile(path)}
                onAddLink={addLink}
                onAddGroup={addGroup}
                onAddShape={addShape}
                onShape={setShape}
                onEnds={(ends) => changeEdges((edge) => withEnds(edge, ends))}
                onReverse={() => changeEdges(reversed)}
                onLineStyle={(style) =>
                    changeEdges((edge) => withLineStyle(edge, style))
                }
                onColor={setColor}
                onDelete={deleteSelection}
                onUndo={doc.undo}
                onRedo={doc.redo}
                onZoom={(factor) => zoomAround(factor)}
                onFit={fit}
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
