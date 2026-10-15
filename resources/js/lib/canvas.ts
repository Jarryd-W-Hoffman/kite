/**
 * JSON Canvas (jsoncanvas.org): the open format Kite's canvases are saved in, shared with
 * Obsidian and others. Positions and sizes are in canvas units; fields Kite doesn't use are
 * carried along untouched so other apps' additions survive a save.
 */

export type CanvasSide = 'top' | 'right' | 'bottom' | 'left';

export type CanvasEnd = 'none' | 'arrow';

type NodeBase = {
    id: string;
    x: number;
    y: number;
    width: number;
    height: number;
    color?: string;
    [extra: string]: unknown;
};

export type CanvasTextNode = NodeBase & { type: 'text'; text: string };

export type CanvasFileNode = NodeBase & {
    type: 'file';
    file: string;
    subpath?: string;
};

export type CanvasLinkNode = NodeBase & { type: 'link'; url: string };

export type CanvasGroupNode = NodeBase & {
    type: 'group';
    label?: string;
    background?: string;
    backgroundStyle?: 'cover' | 'ratio' | 'repeat';
};

export type CanvasNode =
    | CanvasTextNode
    | CanvasFileNode
    | CanvasLinkNode
    | CanvasGroupNode;

export type CanvasEdge = {
    id: string;
    fromNode: string;
    toNode: string;
    fromSide?: CanvasSide;
    toSide?: CanvasSide;
    fromEnd?: CanvasEnd;
    toEnd?: CanvasEnd;
    color?: string;
    label?: string;
    [extra: string]: unknown;
};

export type CanvasData = { nodes: CanvasNode[]; edges: CanvasEdge[] };

/** What a file card shows, as described by the server. */
export type CanvasFile =
    | {
          kind: 'note';
          note_id: number;
          title: string;
          html: string;
          locked: boolean;
      }
    | { kind: 'image' | 'file'; url: string }
    | { kind: 'missing' };

export type Point = { x: number; y: number };

export type Rect = { x: number; y: number; width: number; height: number };

/** The format's six preset colours, in its order: red, orange, yellow, green, cyan, purple. */
export const PRESET_COLORS: Record<string, string> = {
    '1': '#e5484d',
    '2': '#f76b15',
    '3': '#e2a300',
    '4': '#30a46c',
    '5': '#00a2c7',
    '6': '#8e4ec6',
};

/** A card or edge colour as CSS, or null for the default. */
export function colorValue(color: string | undefined): string | null {
    if (!color) {
        return null;
    }

    return (
        PRESET_COLORS[color] ?? (/^#[0-9a-f]{6}$/i.test(color) ? color : null)
    );
}

export const DEFAULT_SIZES: Record<
    CanvasNode['type'],
    { width: number; height: number }
> = {
    text: { width: 260, height: 140 },
    file: { width: 400, height: 400 },
    link: { width: 400, height: 120 },
    group: { width: 600, height: 400 },
};

/** A new id, 16 hex characters like Obsidian's. */
export function newId(): string {
    const bytes = new Uint8Array(8);

    crypto.getRandomValues(bytes);

    return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join(
        '',
    );
}

/** The middle of one side of a card. */
export function anchor(node: Rect, side: CanvasSide): Point {
    switch (side) {
        case 'top':
            return { x: node.x + node.width / 2, y: node.y };
        case 'bottom':
            return { x: node.x + node.width / 2, y: node.y + node.height };
        case 'left':
            return { x: node.x, y: node.y + node.height / 2 };
        case 'right':
            return { x: node.x + node.width, y: node.y + node.height / 2 };
    }
}

const NORMALS: Record<CanvasSide, Point> = {
    top: { x: 0, y: -1 },
    right: { x: 1, y: 0 },
    bottom: { x: 0, y: 1 },
    left: { x: -1, y: 0 },
};

/** The side of `from` that faces `to`, for edges saved without sides. */
export function facingSide(from: Rect, to: Rect): CanvasSide {
    const dx = to.x + to.width / 2 - (from.x + from.width / 2);
    const dy = to.y + to.height / 2 - (from.y + from.height / 2);

    if (Math.abs(dx) * from.height > Math.abs(dy) * from.width) {
        return dx > 0 ? 'right' : 'left';
    }

    return dy > 0 ? 'bottom' : 'top';
}

/** The side of a card nearest a point, for dropping a connection onto it. */
export function nearestSide(node: Rect, point: Point): CanvasSide {
    const distances: [CanvasSide, number][] = [
        ['top', Math.abs(point.y - node.y)],
        ['bottom', Math.abs(point.y - (node.y + node.height))],
        ['left', Math.abs(point.x - node.x)],
        ['right', Math.abs(point.x - (node.x + node.width))],
    ];

    return distances.sort((a, b) => a[1] - b[1])[0][0];
}

/**
 * A curved edge leaving and entering its cards straight out of their sides. Returns the SVG
 * path, its midpoint (for the label) and the direction it arrives from (for the arrow).
 */
export function edgeGeometry(
    from: Point,
    fromSide: CanvasSide,
    to: Point,
    toSide: CanvasSide | null,
): { path: string; middle: Point } {
    const distance = Math.hypot(to.x - from.x, to.y - from.y);
    const reach = Math.min(Math.max(distance / 2, 40), 200);
    const start = NORMALS[fromSide];
    const end = toSide ? NORMALS[toSide] : { x: 0, y: 0 };
    const c1 = { x: from.x + start.x * reach, y: from.y + start.y * reach };
    const c2 = { x: to.x + end.x * reach, y: to.y + end.y * reach };
    // The point halfway along a cubic Bézier.
    const middle = {
        x: (from.x + 3 * c1.x + 3 * c2.x + to.x) / 8,
        y: (from.y + 3 * c1.y + 3 * c2.y + to.y) / 8,
    };

    return {
        path: `M ${from.x} ${from.y} C ${c1.x} ${c1.y}, ${c2.x} ${c2.y}, ${to.x} ${to.y}`,
        middle,
    };
}

export function contains(outer: Rect, inner: Rect): boolean {
    return (
        inner.x >= outer.x &&
        inner.y >= outer.y &&
        inner.x + inner.width <= outer.x + outer.width &&
        inner.y + inner.height <= outer.y + outer.height
    );
}

export function intersects(a: Rect, b: Rect): boolean {
    return (
        a.x < b.x + b.width &&
        b.x < a.x + a.width &&
        a.y < b.y + b.height &&
        b.y < a.y + a.height
    );
}

/** The smallest rectangle around every card, or null for an empty canvas. */
export function bounds(nodes: Rect[]): Rect | null {
    if (nodes.length === 0) {
        return null;
    }

    const left = Math.min(...nodes.map((node) => node.x));
    const top = Math.min(...nodes.map((node) => node.y));
    const right = Math.max(...nodes.map((node) => node.x + node.width));
    const bottom = Math.max(...nodes.map((node) => node.y + node.height));

    return { x: left, y: top, width: right - left, height: bottom - top };
}

export type Viewport = { x: number; y: number; zoom: number };

export const MIN_ZOOM = 0.1;
export const MAX_ZOOM = 2.5;

export function clampZoom(zoom: number): number {
    return Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, zoom));
}

/** A viewport showing every card, with some room around them, never zoomed past 100%. */
export function fitViewport(
    nodes: Rect[],
    width: number,
    height: number,
    padding = 64,
): Viewport {
    const box = bounds(nodes);

    if (!box || width <= 0 || height <= 0) {
        return { x: width / 2, y: height / 2, zoom: 1 };
    }

    const zoom = clampZoom(
        Math.min(
            1,
            (width - padding * 2) / box.width,
            (height - padding * 2) / box.height,
        ),
    );

    return {
        x: width / 2 - (box.x + box.width / 2) * zoom,
        y: height / 2 - (box.y + box.height / 2) * zoom,
        zoom,
    };
}

/** Round to the 20-unit grid cards snap to, as Obsidian's do. */
export function snap(value: number): number {
    return Math.round(value / 20) * 20;
}

/**
 * Move a new card off any cards it would cover: to the right, then on to the next row down, a
 * gap apart, so cards added in a row line up instead of stacking.
 */
export function freeSpot(card: Rect, nodes: Rect[], gap = 40): Rect {
    const solid = nodes.filter(
        (node) =>
            !('type' in node) || (node as { type?: string }).type !== 'group',
    );
    let spot = card;

    for (
        let attempt = 0;
        attempt < 50 && solid.some((node) => intersects(spot, node));
        attempt++
    ) {
        const blocking = solid.filter((node) => intersects(spot, node));
        const right = Math.max(...blocking.map((node) => node.x + node.width));

        spot =
            attempt % 6 === 5
                ? { ...card, y: spot.y + card.height + gap }
                : { ...spot, x: snap(right + gap) };
    }

    return spot;
}
