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

/**
 * The middle of one side of a card, where connections meet it. A parallelogram's slanted sides
 * are met halfway along their slant.
 */
export function anchor(node: Rect & Styled, side: CanvasSide): Point {
    const slant =
        shapeOf(node) === 'parallelogram'
            ? parallelogramSlant(node.width, node.height) / 2
            : 0;

    switch (side) {
        case 'top':
            return { x: node.x + node.width / 2, y: node.y };
        case 'bottom':
            return { x: node.x + node.width / 2, y: node.y + node.height };
        case 'left':
            return { x: node.x + slant, y: node.y + node.height / 2 };
        case 'right':
            return {
                x: node.x + node.width - slant,
                y: node.y + node.height / 2,
            };
    }
}

/**
 * Shapes and line styles aren't part of JSON Canvas. Kite keeps them where Obsidian's Advanced
 * Canvas plugin does, in `styleAttributes`, using its names, so diagrams look the same there.
 */
type Styled = {
    type?: string;
    styleAttributes?: unknown;
    [key: string]: unknown;
};

/** The usual flowchart shapes a text card can take. */
export type CanvasShape =
    | 'rectangle'
    | 'pill'
    | 'diamond'
    | 'parallelogram'
    | 'circle'
    | 'predefined-process'
    | 'document'
    | 'database';

/** Each shape, what it's for in a flowchart, and the size a new one starts at. */
export const SHAPES: {
    shape: CanvasShape;
    label: string;
    width: number;
    height: number;
}[] = [
    { shape: 'rectangle', label: 'Process', width: 220, height: 100 },
    { shape: 'pill', label: 'Start or end', width: 220, height: 80 },
    { shape: 'diamond', label: 'Decision', width: 220, height: 140 },
    {
        shape: 'parallelogram',
        label: 'Input or output',
        width: 240,
        height: 100,
    },
    { shape: 'circle', label: 'Connector', width: 120, height: 120 },
    {
        shape: 'predefined-process',
        label: 'Predefined process',
        width: 240,
        height: 100,
    },
    { shape: 'document', label: 'Document', width: 220, height: 120 },
    { shape: 'database', label: 'Database', width: 160, height: 140 },
];

function styleAttributes(item: Styled): Record<string, unknown> {
    const attributes = item.styleAttributes;

    return typeof attributes === 'object' &&
        attributes !== null &&
        !Array.isArray(attributes)
        ? (attributes as Record<string, unknown>)
        : {};
}

/** Set or clear one style attribute, keeping the rest (other apps may have added some). */
function withStyleAttribute<T extends Styled>(
    item: T,
    key: string,
    value: string | null,
): T {
    const { [key]: _removed, ...rest } = styleAttributes(item);
    const attributes = value === null ? rest : { ...rest, [key]: value };
    const { styleAttributes: _old, ...plain } = item;

    return (
        Object.keys(attributes).length === 0
            ? plain
            : { ...plain, styleAttributes: attributes }
    ) as T;
}

/** A text card's shape, or null for an ordinary card. */
export function shapeOf(node: Styled): CanvasShape | null {
    const shape = styleAttributes(node).shape;

    return node.type === 'text' &&
        SHAPES.some((option) => option.shape === shape)
        ? (shape as CanvasShape)
        : null;
}

/** A card with another shape, or as an ordinary card for null. */
export function withShape<T extends Styled>(
    node: T,
    shape: CanvasShape | null,
): T {
    return withStyleAttribute(node, 'shape', shape);
}

/** How far a parallelogram's top is pushed right of its bottom. */
export function parallelogramSlant(width: number, height: number): number {
    return Math.min(height * 0.4, width * 0.25);
}

/**
 * A shape's outline as SVG paths in the card's own coordinates: the outline, filled, and any
 * lines drawn inside it (a predefined process's side bars, a database's rim).
 */
export function shapePaths(
    shape: CanvasShape,
    width: number,
    height: number,
): { outline: string; details?: string } {
    const w = width;
    const h = height;

    switch (shape) {
        case 'rectangle':
            return { outline: roundedRect(w, h, 4) };
        case 'pill':
            return { outline: roundedRect(w, h, Math.min(w, h) / 2) };
        case 'diamond':
            return {
                outline: `M ${w / 2} 0 L ${w} ${h / 2} L ${w / 2} ${h} L 0 ${h / 2} Z`,
            };
        case 'parallelogram': {
            const slant = parallelogramSlant(w, h);

            return {
                outline: `M ${slant} 0 L ${w} 0 L ${w - slant} ${h} L 0 ${h} Z`,
            };
        }
        case 'circle':
            return {
                outline: `M 0 ${h / 2} A ${w / 2} ${h / 2} 0 1 1 ${w} ${h / 2} A ${w / 2} ${h / 2} 0 1 1 0 ${h / 2} Z`,
            };
        case 'predefined-process': {
            const bar = Math.min(14, w / 8);

            return {
                outline: roundedRect(w, h, 4),
                details: `M ${bar} 0 V ${h} M ${w - bar} 0 V ${h}`,
            };
        }
        case 'document': {
            const wave = Math.min(h * 0.12, 14);

            return {
                outline: `M 0 0 H ${w} V ${h - wave} Q ${(w * 3) / 4} ${h - wave * 2.5} ${w / 2} ${h - wave} T 0 ${h - wave} Z`,
            };
        }
        case 'database': {
            const rim = Math.min(h * 0.14, w * 0.14, 18);

            return {
                outline: `M 0 ${rim} A ${w / 2} ${rim} 0 0 1 ${w} ${rim} V ${h - rim} A ${w / 2} ${rim} 0 0 1 0 ${h - rim} Z`,
                details: `M 0 ${rim} A ${w / 2} ${rim} 0 0 0 ${w} ${rim}`,
            };
        }
    }
}

function roundedRect(w: number, h: number, radius: number): string {
    const r = Math.min(radius, w / 2, h / 2);

    return `M ${r} 0 H ${w - r} A ${r} ${r} 0 0 1 ${w} ${r} V ${h - r} A ${r} ${r} 0 0 1 ${w - r} ${h} H ${r} A ${r} ${r} 0 0 1 0 ${h - r} V ${r} A ${r} ${r} 0 0 1 ${r} 0 Z`;
}

/**
 * Room to leave inside a shape so its text stays within the outline, in canvas units.
 */
export function shapeInsets(
    shape: CanvasShape,
    width: number,
    height: number,
): { top: number; right: number; bottom: number; left: number } {
    const even = (x: number, y: number) => ({
        top: y,
        right: x,
        bottom: y,
        left: x,
    });

    switch (shape) {
        case 'diamond':
            return even(width / 4, height / 4);
        case 'circle':
            return even(width * 0.15, height * 0.15);
        case 'parallelogram':
            return even(parallelogramSlant(width, height) + 4, 8);
        case 'predefined-process':
            return even(Math.min(14, width / 8) + 8, 8);
        case 'document':
            return { ...even(10, 8), bottom: Math.min(height * 0.12, 14) * 2 };
        case 'database':
            return {
                ...even(10, 8),
                top: Math.min(height * 0.14, width * 0.14, 18) * 2 + 4,
            };
        default:
            return even(12, 8);
    }
}

/** How a connection's line is drawn. */
export type LineStyle = 'solid' | 'long-dashed' | 'short-dashed' | 'dotted';

export const LINE_STYLES: { style: LineStyle; label: string }[] = [
    { style: 'solid', label: 'Solid line' },
    { style: 'long-dashed', label: 'Dashed line' },
    { style: 'dotted', label: 'Dotted line' },
];

export function lineStyleOf(edge: CanvasEdge): LineStyle {
    const path = styleAttributes(edge).path;

    return path === 'long-dashed' ||
        path === 'short-dashed' ||
        path === 'dotted'
        ? path
        : 'solid';
}

export function withLineStyle(edge: CanvasEdge, style: LineStyle): CanvasEdge {
    return withStyleAttribute(edge, 'path', style === 'solid' ? null : style);
}

/** The SVG dash pattern for a line style, at a 2-unit stroke. */
export const DASHES: Record<LineStyle, string | undefined> = {
    solid: undefined,
    'long-dashed': '10 6',
    'short-dashed': '5 5',
    dotted: '0.5 6',
};

/** Which ends of a connection have arrowheads. */
export type CanvasEnds = 'end' | 'both' | 'none';

export function endsOf(edge: CanvasEdge): CanvasEnds {
    const from = (edge.fromEnd ?? 'none') === 'arrow';
    const to = (edge.toEnd ?? 'arrow') === 'arrow';

    return from && to ? 'both' : from || to ? 'end' : 'none';
}

export function withEnds(edge: CanvasEdge, ends: CanvasEnds): CanvasEdge {
    return {
        ...edge,
        fromEnd: ends === 'both' ? 'arrow' : 'none',
        toEnd: ends === 'none' ? 'none' : 'arrow',
    };
}

/** The same connection running the other way: its ends, sides and arrowheads swap. */
export function reversed(edge: CanvasEdge): CanvasEdge {
    const { fromNode, toNode, fromSide, toSide, fromEnd, toEnd, ...rest } =
        edge;

    return {
        ...rest,
        fromNode: toNode,
        toNode: fromNode,
        ...(toSide === undefined ? {} : { fromSide: toSide }),
        ...(fromSide === undefined ? {} : { toSide: fromSide }),
        fromEnd: toEnd ?? 'arrow',
        toEnd: fromEnd ?? 'none',
    };
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
