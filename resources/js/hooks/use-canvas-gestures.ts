import { useRef, useState } from 'react';
import type { PointerEvent as ReactPointerEvent } from 'react';
import type { useCanvasDocument } from '@/hooks/use-canvas-document';
import type { useCanvasViewport } from '@/hooks/use-canvas-viewport';
import { contains, intersects, snap } from '@/lib/canvas';
import type {
    CanvasNode,
    CanvasSide,
    Point,
    Rect,
    Viewport,
} from '@/lib/canvas';

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

type Options = {
    doc: ReturnType<typeof useCanvasDocument>;
    viewport: ReturnType<typeof useCanvasViewport>;
    selected: Set<string>;
    setSelected: (selected: Set<string>) => void;
    nodeById: Map<string, CanvasNode>;
    /** A connection dragged from a card's side was let go at a point, over a card or not. */
    onConnectEnd: (
        from: string,
        side: CanvasSide,
        point: Point,
        target: CanvasNode | undefined,
    ) => void;
};

/**
 * Dragging on the canvas: panning (middle button or Space), moving and resizing cards, drawing a
 * connection from a card's side, and selecting cards in a box. A move or resize is one step in
 * the canvas's history, snapped to the grid when it ends.
 */
export function useCanvasGestures({
    doc,
    viewport: { container, view, setViewport, toCanvas, spaceHeld },
    selected,
    setSelected,
    nodeById,
    onConnectEnd,
}: Options) {
    const { data, readOnly } = doc;
    const [connecting, setConnecting] = useState<{
        from: string;
        side: CanvasSide;
        point: Point;
    } | null>(null);
    const [marquee, setMarquee] = useState<Rect | null>(null);
    const gesture = useRef<Gesture | null>(null);

    const onPointerDown = (event: ReactPointerEvent<HTMLDivElement>) => {
        if (event.button === 2) {
            return;
        }

        container.current?.focus({ preventScroll: true });
        event.currentTarget.setPointerCapture(event.pointerId);

        if (event.button === 1 || spaceHeld.current) {
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

            onConnectEnd(current.from, current.side, point, target);
        }
    };

    return {
        connecting,
        marquee,
        onPointerDown,
        onPointerMove,
        onPointerUp,
        onConnectStart,
        onResizeStart,
    };
}
